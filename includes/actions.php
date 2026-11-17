<?php
function handle_action(array $u): void {
    if($_SERVER['REQUEST_METHOD']!=='POST')return;
    verify_csrf();$action=(string)($_POST['action']??'');
    try {
        db()->beginTransaction();
        if($action==='staff_save') {
            if($u['role']!=='admin')throw new RuntimeException('Access denied.');
            $role=post('staff_role');if(!in_array($role,['doctor','receptionist'],true))throw new RuntimeException('Invalid staff role.');
            $id=isset($_POST['id']) && $_POST['id']!==''?positive_id('id'):0;
            $name=post('name',120);$email=post('email',190);if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email.');
            $status=post('status');if(!in_array($status,['active','inactive'],true))throw new RuntimeException('Invalid status.');
            if($id){if(!one('SELECT id FROM users WHERE id=? AND role=?',[$id,$role]))throw new RuntimeException('Staff account not found.');query('UPDATE users SET name=?,email=?,status=? WHERE id=?',[$name,$email,$status,$id]);}
            else { $password=post('password',200);if(strlen($password)<8)throw new RuntimeException('Password must contain at least 8 characters.');query('INSERT INTO users(name,email,password,role,status) VALUES(?,?,?,?,?)',[$name,$email,password_hash($password,PASSWORD_DEFAULT),$role,$status]);$id=(int)db()->lastInsertId(); }
            $phone=post('phone',30);
            if($role==='doctor') {
                $exp=filter_var($_POST['experience']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>80]]);if($exp===false)throw new RuntimeException('Experience must be between 0 and 80.');
                query('INSERT INTO doctors(user_id,specialization,qualification,experience,phone,availability) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE specialization=VALUES(specialization),qualification=VALUES(qualification),experience=VALUES(experience),phone=VALUES(phone),availability=VALUES(availability)',[$id,post('specialization',120),post('qualification',190),$exp,$phone,post('availability',255)]);
            }else query('INSERT INTO receptionists(user_id,phone,address) VALUES(?,?,?) ON DUPLICATE KEY UPDATE phone=VALUES(phone),address=VALUES(address)',[$id,$phone,post('address',500)]);
        } elseif($action==='patient_save') {
            if(!in_array($u['role'],['receptionist','patient'],true))throw new RuntimeException('Access denied.');
            $id=!empty($_POST['id'])?positive_id('id'):0;
            if($u['role']==='patient' && !$id)throw new RuntimeException('Access denied.');
            $existing=$id?accessible_patient($id,$u):null;
            $name=post('name',120);$email=post('email',190);if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email.');
            $age=filter_var($_POST['age']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>130]]);if($age===false)throw new RuntimeException('Age must be between 0 and 130.');
            $gender=post('gender');if(!in_array($gender,['Female','Male','Other'],true))throw new RuntimeException('Invalid gender.');
            $phone=post('phone',30);$address=post('address',500);$history=$u['role']==='patient'?$existing['medical_history']:post('medical_history',5000,false);
            if($existing){$uid=$existing['user_id'];query('UPDATE users SET name=?,email=? WHERE id=?',[$name,$email,$uid]);query('UPDATE patients SET age=?,gender=?,phone=?,address=?,medical_history=? WHERE id=?',[$age,$gender,$phone,$address,$history,$id]);}
            else {$password=post('password',200);if(strlen($password)<8)throw new RuntimeException('Password must contain at least 8 characters.');query("INSERT INTO users(name,email,password,role) VALUES(?,?,?,'patient')",[$name,$email,password_hash($password,PASSWORD_DEFAULT)]);$uid=db()->lastInsertId();query('INSERT INTO patients(user_id,age,gender,phone,address,medical_history) VALUES(?,?,?,?,?,?)',[$uid,$age,$gender,$phone,$address,$history]);}
        } elseif($action==='patient_delete') {
            if($u['role']!=='receptionist')throw new RuntimeException('Access denied.');
            $p=accessible_patient(positive_id('id'),$u);
            if(one('SELECT id FROM medical_records WHERE patient_id=? LIMIT 1',[$p['id']]))throw new RuntimeException('Patients with medical records cannot be deleted.');
            query('DELETE FROM appointments WHERE patient_id=?',[$p['id']]);query('DELETE FROM patients WHERE id=?',[$p['id']]);query('DELETE FROM users WHERE id=?',[$p['user_id']]);
        } elseif($action==='appointment_save') {
            if($u['role']!=='receptionist')throw new RuntimeException('Access denied.');
            $id=!empty($_POST['id'])?positive_id('id'):0;
            if($id && !one('SELECT id FROM appointments WHERE id=?',[$id]))throw new RuntimeException('Appointment not found.');
            $pid=positive_id('patient_id');$did=positive_id('doctor_id');
            if(!one("SELECT d.id FROM doctors d JOIN users u ON u.id=d.user_id WHERE d.id=? AND u.status='active' FOR UPDATE",[$did]))throw new RuntimeException('Choose an active doctor.');
            if(!one('SELECT id FROM patients WHERE id=? FOR UPDATE',[$pid]))throw new RuntimeException('Patient not found.');
            $date=post('appointment_date',10);$time=post('appointment_time',5);$dt=DateTime::createFromFormat('!Y-m-d H:i',$date.' '.$time);
            if(!$dt || $dt->format('Y-m-d H:i')!==$date.' '.$time)throw new RuntimeException('Enter a valid date and time.');
            $status=post('status');if(!in_array($status,['Pending','Confirmed','Completed','Cancelled'],true))throw new RuntimeException('Invalid appointment status.');
            if(in_array($status,['Pending','Confirmed'],true) && one("SELECT id FROM appointments WHERE (doctor_id=? OR patient_id=?) AND appointment_date=? AND appointment_time=? AND status IN ('Pending','Confirmed') AND id<>?",[$did,$pid,$date,$time,$id]))throw new RuntimeException('Doctor or patient already has an appointment at this time.');
            if($id) query('UPDATE appointments SET patient_id=?,doctor_id=?,appointment_date=?,appointment_time=?,status=? WHERE id=?',[$pid,$did,$date,$time,$status,$id]);
            else query('INSERT INTO appointments(patient_id,doctor_id,appointment_date,appointment_time,status) VALUES(?,?,?,?,?)',[$pid,$did,$date,$time,$status]);
        } elseif(in_array($action,['appointment_cancel','appointment_status'],true)) {
            if(!in_array($u['role'],['doctor','receptionist'],true))throw new RuntimeException('Access denied.');
            $id=positive_id('id');$a=one('SELECT a.*,d.user_id FROM appointments a JOIN doctors d ON d.id=a.doctor_id WHERE a.id=? FOR UPDATE',[$id]);
            if(!$a || ($u['role']==='doctor' && $a['user_id']!=$u['id']))throw new RuntimeException('Appointment unavailable or access denied.');
            $status=$action==='appointment_cancel'?'Cancelled':post('status');
            if($action==='appointment_cancel' && $u['role']!=='receptionist')throw new RuntimeException('Access denied.');
            // Confirm/reschedule through reception to recheck slot conflicts.
            if(!in_array($status,['Completed','Cancelled'],true) || in_array($a['status'],['Completed','Cancelled'],true))throw new RuntimeException('Only open appointments can be completed or cancelled.');
            query('UPDATE appointments SET status=? WHERE id=?',[$status,$id]);
        } elseif($action==='record_save') {
            if($u['role']!=='doctor')throw new RuntimeException('Access denied.');
            $pid=positive_id('patient_id');accessible_patient($pid,$u);$doctor=one('SELECT id FROM doctors WHERE user_id=?',[$u['id']]);$id=!empty($_POST['id'])?positive_id('id'):0;
            $data=[post('diagnosis',5000),post('treatment',5000),post('prescription',5000)];
            if($id){if(!one('SELECT id FROM medical_records WHERE id=? AND doctor_id=? AND patient_id=?',[$id,$doctor['id'],$pid]))throw new RuntimeException('Only the author can edit this record.');query('UPDATE medical_records SET diagnosis=?,treatment=?,prescription=? WHERE id=?',array_merge($data,[$id]));}
            else query('INSERT INTO medical_records(diagnosis,treatment,prescription,patient_id,doctor_id) VALUES(?,?,?,?,?)',array_merge($data,[$pid,$doctor['id']]));
        } else throw new RuntimeException('Unknown operation.');
        db()->commit();$_SESSION['flash']='Changes saved successfully.';
        redirect(ROLE.'/'.PAGE.'.php');
    } catch(Throwable $ex) {
        if(db()->inTransaction())db()->rollBack();
        if($ex instanceof PDOException){error_log($ex->getMessage());$GLOBALS['error']=$ex->getCode()==='23000'?'This email is already registered, or the change conflicts with linked records.':'The change could not be saved. Please try again.';}
        else $GLOBALS['error']=$ex->getMessage();
    }
}
