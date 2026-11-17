<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}
// Run only against a disposable imported database; this creates test fixtures.
require __DIR__.'/../config/database.php';
if (!str_ends_with(getenv('DB_NAME') ?: '', '_test')) exit("Use a disposable DB_NAME ending in _test. See README.\n");
$base=getenv('TEST_BASE_URL') ?: 'http://127.0.0.1:8081';$checks=0;
function check(bool $ok,string $message): void {global $checks;if(!$ok)throw new RuntimeException('FAIL: '.$message);$checks++;echo "PASS: $message\n";}
function sql(string $query,array $params=[]): PDOStatement {$s=db()->prepare($query);$s->execute($params);return $s;}
class Client {
    public string $cookie='';public string $token='';public int $status=0;public string $location='';
    function request(string $path,?array $data=null): string {global $base;
        $headers="Cookie: {$this->cookie}\r\n";
        if($data!==null){$data['csrf']??=$this->token;$headers.="Content-Type: application/x-www-form-urlencoded\r\n";}
        $context=stream_context_create(['http'=>['method'=>$data===null?'GET':'POST','header'=>$headers,'content'=>$data===null?'':http_build_query($data),'follow_location'=>0,'ignore_errors'=>true]]);
        $html=file_get_contents($base.$path,false,$context);$this->location='';
        foreach($http_response_header as $h){if(preg_match('/^HTTP\/\S+ (\d+)/',$h,$m))$this->status=(int)$m[1];if(preg_match('/^Set-Cookie: (PHPSESSID=[^;]+)/i',$h,$m))$this->cookie=$m[1];if(preg_match('/^Location: (.*)/i',$h,$m))$this->location=$m[1];}
        if(preg_match('/name="csrf" value="([^"]+)"/',$html,$m))$this->token=$m[1];
        check($this->status<500 && !str_contains($html,'Warning:') && !str_contains($html,'Fatal error:'),"$path returns without server errors");
        return $html;
    }
    function login(string $email,string $role): void {$this->request('/login.php');$this->request('/login.php',['email'=>$email,'password'=>'Carewell123!']);check($this->status===302 && $this->location==="/$role/dashboard.php","$email logs in as $role");$this->request($this->location);}
    function save(string $path,array $data): void {$html=$this->request($path,$data);if($this->status!==302 && preg_match('/<div class="alert error"[^>]*>(.*?)<\/div>/s',$html,$m))fwrite(STDERR,strip_tags($m[1])."\n");check($this->status===302,"$path saves and redirects");$this->request($this->location);}
}
try {
$anon=new Client();$anon->request('/admin/dashboard.php');check($anon->status===302,'Anonymous dashboard access redirects');
$admin=new Client();$admin->login('admin@carewell.test','admin');
$doctor=new Client();$doctor->login('sarah@carewell.test','doctor');
$otherDoctor=new Client();$otherDoctor->login('james@carewell.test','doctor');
$reception=new Client();$reception->login('reception@carewell.test','receptionist');
$patient=new Client();$patient->login('olivia@carewell.test','patient');
foreach(['noah','ava'] as $name){$c=new Client();$c->login("$name@carewell.test",'patient');}
foreach(['admin'=>['dashboard','doctors','receptionists','patients','appointments'],'doctor'=>['dashboard','patients','appointments','medical_records','profile'],'receptionist'=>['dashboard','patients','appointments','doctors','profile'],'patient'=>['dashboard','profile','appointments','medical_records']] as $role=>$pages){$client=match($role){'admin'=>$admin,'doctor'=>$doctor,'receptionist'=>$reception,'patient'=>$patient};foreach($pages as $page)$client->request("/$role/$page.php");}
$patient->request('/admin/doctors.php');check($patient->status===403,'Patient cannot access admin pages');
$html=$patient->request('/patient/medical_records.php?patient_id=2');check(!str_contains($html,'Blood pressure review'),'Patient cannot read another patient record');
$html=$doctor->request('/doctor/medical_records.php?patient_id=2');check(!str_contains($html,'Blood pressure review'),'Doctor cannot read unassigned records');
$html=$doctor->request('/doctor/patients.php');check(!str_contains($html,'Noah Williams'),'Unassigned patients excluded from doctor search');
$patient->request('/patient/profile.php',['action'=>'patient_save','csrf'=>'bad']);check($patient->status===403,'Forged CSRF token rejected');
$patient->request('/patient/profile.php');
$forged=['action'=>'patient_save','id'=>2,'name'=>'Forged','email'=>'noah@carewell.test','age'=>45,'gender'=>'Male','phone'=>'123','address'=>'Test'];
$html=$patient->request('/patient/profile.php',$forged);check(str_contains($html,'access denied') && sql('SELECT name FROM users WHERE id=6')->fetchColumn()==='Noah Williams','Patient cannot modify another profile');
$reception->request('/receptionist/patients.php');
$data=['action'=>'patient_save','name'=>'Smoke Patient','email'=>'smoke-patient@carewell.test','password'=>'Carewell123!','age'=>31,'gender'=>'Other','phone'=>'0123456789','address'=>'Test Street','medical_history'=>'Smoke history'];
$reception->save('/receptionist/patients.php',$data);
$pid=(int)sql("SELECT p.id FROM patients p JOIN users u ON u.id=p.user_id WHERE u.email='smoke-patient@carewell.test'")->fetchColumn();check($pid>0,'Registration persists in MySQL');
$data['id']=$pid;$data['name']='Smoke Patient Updated';$reception->save('/receptionist/patients.php',$data);
$html=$reception->request('/receptionist/patients.php?q=Smoke');check(str_contains($html,'Smoke Patient Updated') && !str_contains($html,'Olivia Bennett'),'Patient search filters database records');
$data['email']='olivia@carewell.test';$html=$reception->request('/receptionist/patients.php',$data);check(str_contains($html,'already registered'),'Duplicate email handled');
$appt=['action'=>'appointment_save','patient_id'=>$pid,'doctor_id'=>1,'appointment_date'=>'2030-06-10','appointment_time'=>'15:00','status'=>'Confirmed'];
$reception->save('/receptionist/appointments.php',$appt);$aid=(int)sql('SELECT MAX(id) FROM appointments')->fetchColumn();
$html=$reception->request('/receptionist/appointments.php',$appt);check(str_contains($html,'already has an appointment'),'Doctor/patient slot conflict blocked');
$appt['id']=$aid;$appt['appointment_time']='15:30';$reception->save('/receptionist/appointments.php',$appt);check(sql('SELECT appointment_time FROM appointments WHERE id=?',[$aid])->fetchColumn()==='15:30:00','Appointment update persists');
$doctor->request('/doctor/medical_records.php');
$record=['action'=>'record_save','patient_id'=>$pid,'diagnosis'=>'Smoke diagnosis','treatment'=>'Smoke treatment','prescription'=>'Smoke prescription'];
$doctor->save('/doctor/medical_records.php',$record);$rid=(int)sql('SELECT MAX(id) FROM medical_records')->fetchColumn();
$record['id']=$rid;$record['prescription']='Updated smoke prescription';$doctor->save('/doctor/medical_records.php',$record);
check(sql('SELECT prescription FROM medical_records WHERE id=?',[$rid])->fetchColumn()==='Updated smoke prescription','Prescription update persists');
$otherDoctor->request('/doctor/medical_records.php');$html=$otherDoctor->request('/doctor/medical_records.php',$record);check(str_contains($html,'access denied'),'Unassigned doctor cannot edit records');
$record['patient_id']=2;$record['id']=2;$html=$doctor->request('/doctor/medical_records.php',$record);check(str_contains($html,'access denied'),'Forged record ownership rejected');
$reception->request('/receptionist/patients.php');$html=$reception->request('/receptionist/patients.php',['action'=>'patient_delete','id'=>$pid]);check(str_contains($html,'cannot be deleted'),'Clinical history prevents patient deletion');
$doctor->request('/doctor/appointments.php');$doctor->save('/doctor/appointments.php',['action'=>'appointment_status','id'=>$aid,'status'=>'Completed']);check(sql('SELECT status FROM appointments WHERE id=?',[$aid])->fetchColumn()==='Completed','Doctor completes assigned appointment');
$appt['id']='';$appt['appointment_time']='16:00';$reception->request('/receptionist/appointments.php');$reception->save('/receptionist/appointments.php',$appt);$cancelId=(int)sql('SELECT MAX(id) FROM appointments')->fetchColumn();
$reception->save('/receptionist/appointments.php',['action'=>'appointment_cancel','id'=>$cancelId]);check(sql('SELECT status FROM appointments WHERE id=?',[$cancelId])->fetchColumn()==='Cancelled','Appointment cancellation persists');
foreach(['doctor','receptionist'] as $role){$page=$role==='doctor'?'doctors':'receptionists';$admin->request('/admin/'.$page.'.php');$staff=['action'=>'staff_save','staff_role'=>$role,'name'=>'Smoke Staff','email'=>'smoke-'.$role.'@carewell.test','password'=>'Carewell123!','status'=>'active','phone'=>'12345','specialization'=>'General Medicine','qualification'=>'MBBS','experience'=>2,'availability'=>'Mon–Fri 09:00–17:00','address'=>'Test address'];$admin->save('/admin/'.$page.'.php',$staff);$id=(int)sql('SELECT id FROM users WHERE email=?',[$staff['email']])->fetchColumn();$c=new Client();$c->login($staff['email'],$role);$staff['id']=$id;$staff['name']='Updated Smoke Staff';$staff['status']='inactive';$admin->save('/admin/'.$page.'.php',$staff);check(sql('SELECT status FROM users WHERE id=?',[$id])->fetchColumn()==='inactive','Admin edits and deactivates '.$role);$c->request('/'.$role.'/dashboard.php');check($c->status===302 && $c->location==='/login.php','Deactivated session revoked');$c->request('/login.php');$html=$c->request('/login.php',['email'=>$staff['email'],'password'=>'Carewell123!']);check(str_contains($html,'inactive account'),'Inactive '.$role.' login blocked');}
$newPatient=new Client();$newPatient->login('smoke-patient@carewell.test','patient');$html=$newPatient->request('/patient/medical_records.php');check(str_contains($html,'Updated smoke prescription'),'Patient sees saved prescription');
$newPatient->request('/patient/profile.php');$data['email']='smoke-patient@carewell.test';$data['address']='Updated patient address';$newPatient->save('/patient/profile.php',$data);check(sql('SELECT address FROM patients WHERE id=?',[$pid])->fetchColumn()==='Updated patient address','Patient updates own profile');
$reception->request('/receptionist/patients.php');unset($data['id']);$data['email']='delete-me@carewell.test';$reception->save('/receptionist/patients.php',$data);$deleteId=(int)sql("SELECT p.id FROM patients p JOIN users u ON u.id=p.user_id WHERE u.email='delete-me@carewell.test'")->fetchColumn();$reception->save('/receptionist/patients.php',['action'=>'patient_delete','id'=>$deleteId]);check(!sql('SELECT id FROM patients WHERE id=?',[$deleteId])->fetch(),'Reception deletes patient without clinical records');
$html=$admin->request('/admin/dashboard.php');$count=(int)sql('SELECT COUNT(*) FROM patients')->fetchColumn();check(str_contains($html,'class="stat-number">'.$count.'</strong>'),'Dashboard patient count matches MySQL');
$patient->request('/patient/profile.php');$patient->request('/logout.php',[]);check($patient->status===302,'Logout redirects');$patient->request('/patient/dashboard.php');check($patient->status===302 && $patient->location==='/login.php','Logged-out session cannot access dashboard');
echo "\n$checks checks passed.\n";
} catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
