<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function respond($data, int $status = 200): void { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); exit; }
if (empty($_SESSION['alogin'])) respond(['error' => 'Authentication required. Please log in again.'], 401);
require_once __DIR__ . '/../includes/config.php';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST') {
    $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || !is_string($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) respond(['error' => 'Security token expired. Refresh the page and try again.'], 403);
    $raw = file_get_contents('php://input');
    $input = json_decode($raw ?: '{}', true);
    if (!is_array($input)) respond(['error' => 'Invalid JSON request.'], 400);
} else { $input = []; }
function classLabel(array $row): string {
    $name = trim((string)($row['ClassName'] ?? ''));
    $section = trim((string)($row['Section'] ?? ''));
    return $section !== '' ? $name . ' (' . $section . ')' : $name;
}
function resolveClass(PDO $dbh, string $label): int {
    $label = trim($label);
    if ($label === '') throw new InvalidArgumentException('Class / semester is required.');
    $name = $label; $section = '';
    if (preg_match('/^(.*?)\s*\(([^()]+)\)\s*$/u', $label, $m)) { $name = trim($m[1]); $section = trim($m[2]); }
    $q = $dbh->prepare('SELECT id FROM tblclasses WHERE ClassName = :name AND COALESCE(Section, \'\') = :section LIMIT 1');
    $q->execute([':name' => $name, ':section' => $section]);
    $id = $q->fetchColumn();
    if ($id) return (int)$id;
    // Accept an exact class name already in the old database even if its section is not shown.
    if ($section === '') {
        $q = $dbh->prepare('SELECT id FROM tblclasses WHERE ClassName = :name ORDER BY id LIMIT 1');
        $q->execute([':name' => $name]); $id = $q->fetchColumn(); if ($id) return (int)$id;
    }
    $numeric = null;
    if (preg_match('/(\d+)/', $name, $m)) $numeric = (int)$m[1];
    $ins = $dbh->prepare('INSERT INTO tblclasses (ClassName, ClassNameNumeric, Section) VALUES (:name, :numeric, :section)');
    $ins->execute([':name' => mb_substr($name, 0, 80), ':numeric' => $numeric, ':section' => mb_substr($section, 0, 5)]);
    return (int)$dbh->lastInsertId();
}
function subjectsForClass(PDO $dbh, int $classId): array {
    $q = $dbh->prepare('SELECT s.id AS subject_id, s.SubjectName AS name FROM tblsubjectcombination sc JOIN tblsubjects s ON s.id = sc.SubjectId WHERE sc.ClassId = :cid AND COALESCE(sc.status, 1) = 1 ORDER BY s.SubjectName');
    $q->execute([':cid' => $classId]); $subjects = $q->fetchAll(PDO::FETCH_ASSOC);
    if (!$subjects) {
        $defaults = ['English', 'Mathematics', 'Science', 'Computer', 'General Knowledge'];
        foreach ($defaults as $name) {
            $find = $dbh->prepare('SELECT id FROM tblsubjects WHERE SubjectName = :name LIMIT 1'); $find->execute([':name' => $name]); $sid = $find->fetchColumn();
            if (!$sid) { $insert = $dbh->prepare('INSERT INTO tblsubjects (SubjectName, SubjectCode) VALUES (:name, :code)'); $insert->execute([':name' => $name, ':code' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 5))]); $sid = $dbh->lastInsertId(); }
            $link = $dbh->prepare('SELECT id FROM tblsubjectcombination WHERE ClassId = :cid AND SubjectId = :sid LIMIT 1'); $link->execute([':cid' => $classId, ':sid' => $sid]);
            if (!$link->fetchColumn()) { $add = $dbh->prepare('INSERT INTO tblsubjectcombination (ClassId, SubjectId, status) VALUES (:cid, :sid, 1)'); $add->execute([':cid' => $classId, ':sid' => $sid]); }
        }
        $q->execute([':cid' => $classId]); $subjects = $q->fetchAll(PDO::FETCH_ASSOC);
    }
    return $subjects;
}
function getStudent(PDO $dbh, int $id): ?array {
    $q = $dbh->prepare('SELECT s.StudentId AS id, s.StudentName AS name, s.RollId AS roll, s.StudentEmail AS email, s.ClassId AS class_id, s.Status AS status, c.ClassName, c.Section FROM tblstudents s LEFT JOIN tblclasses c ON c.id=s.ClassId WHERE s.StudentId=:id LIMIT 1');
    $q->execute([':id'=>$id]); $s=$q->fetch(PDO::FETCH_ASSOC); if (!$s) return null;
    $s['class_name'] = classLabel($s); unset($s['ClassName'], $s['Section']);
    $m=$dbh->prepare('SELECT r.id, r.SubjectId AS subject_id, sub.SubjectName AS subject_name, r.marks FROM tblresult r JOIN tblsubjects sub ON sub.id=r.SubjectId WHERE r.StudentId=:id AND r.ClassId=:class_id ORDER BY r.id ASC');
    $m->execute([':id'=>$id, ':class_id'=>(int)$s['class_id']]); $bySubject=[]; foreach($m->fetchAll(PDO::FETCH_ASSOC) as $row) $bySubject[(string)$row['subject_id']] = ['subject_id'=>(int)$row['subject_id'],'subject_name'=>$row['subject_name'],'marks'=>(int)$row['marks']];
    $s['marks']=array_values($bySubject);
    $s['id']=(int)$s['id']; $s['class_id']=(int)$s['class_id']; $s['status']=(int)$s['status'];
    return $s;
}
function allStudents(PDO $dbh): array {
    $q=$dbh->query('SELECT StudentId FROM tblstudents ORDER BY StudentId DESC'); $ids=$q->fetchAll(PDO::FETCH_COLUMN); $out=[]; foreach($ids as $id){$s=getStudent($dbh,(int)$id); if($s)$out[]=$s;} return $out;
}
try {
    $action = (string)($_GET['action'] ?? 'bootstrap');
    if ($method === 'GET' && $action === 'bootstrap') {
        $students = allStudents($dbh);
        $classes = $dbh->query('SELECT id, ClassName, Section FROM tblclasses ORDER BY ClassName, Section')->fetchAll(PDO::FETCH_ASSOC);
        $classes = array_map(function($c){return ['id'=>(int)$c['id'],'label'=>classLabel($c)];},$classes);
        respond(['students'=>$students,'classes'=>$classes,'admin'=>$_SESSION['alogin']]);
    }
    if ($method === 'GET' && $action === 'subjects') {
        $studentId=(int)($_GET['student_id'] ?? 0); $s=getStudent($dbh,$studentId); if(!$s)respond(['error'=>'Student not found.'],404);
        $subjects=subjectsForClass($dbh,(int)$s['class_id']);
        $existing=[]; foreach($s['marks'] as $m)$existing[(string)$m['subject_id']]=$m['marks'];
        foreach($subjects as &$sub){$sub['subject_id']=(int)$sub['subject_id'];$sub['marks']=$existing[(string)$sub['subject_id']] ?? null;} unset($sub);
        respond(['student_id'=>$studentId,'subjects'=>$subjects]);
    }
    if ($method !== 'POST') respond(['error'=>'Unsupported request method.'],405);
    if ($action === 'save_student') {
        $id=trim((string)($input['id'] ?? '')); $name=trim((string)($input['name'] ?? '')); $roll=trim((string)($input['roll'] ?? '')); $className=trim((string)($input['class_name'] ?? '')); $email=trim((string)($input['email'] ?? ''));
        if($name===''||$roll===''||$className==='')respond(['error'=>'Name, roll number and class are required.'],422);
        if(mb_strlen($name)>100||mb_strlen($roll)>100||mb_strlen($email)>100)respond(['error'=>'One or more fields are too long.'],422);
        if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))respond(['error'=>'Enter a valid email address.'],422);
        $dup=$dbh->prepare('SELECT StudentId FROM tblstudents WHERE RollId=:roll AND StudentId<>:id LIMIT 1');$dup->execute([':roll'=>$roll,':id'=>$id!==''?(int)$id:0]);if($dup->fetchColumn())respond(['error'=>'That roll number is already registered.'],409);
        $classId=resolveClass($dbh,$className);
        if($id!==''){
            $exists=$dbh->prepare('SELECT StudentId FROM tblstudents WHERE StudentId=:id');$exists->execute([':id'=>(int)$id]);if(!$exists->fetchColumn())respond(['error'=>'Student not found.'],404);
            $oldClassQ=$dbh->prepare('SELECT ClassId FROM tblstudents WHERE StudentId=:id'); $oldClassQ->execute([':id'=>(int)$id]); $oldClassId=(int)$oldClassQ->fetchColumn();
            if ($oldClassId !== $classId) { $clear=$dbh->prepare('DELETE FROM tblresult WHERE StudentId=:id'); $clear->execute([':id'=>(int)$id]); }
            $q=$dbh->prepare('UPDATE tblstudents SET StudentName=:name, RollId=:roll, StudentEmail=:email, ClassId=:class_id, UpdationDate=NOW() WHERE StudentId=:id');
            $q->execute([':name'=>$name,':roll'=>$roll,':email'=>$email,':class_id'=>$classId,':id'=>(int)$id]); $savedId=(int)$id;
        } else {
            $q=$dbh->prepare('INSERT INTO tblstudents (StudentName,RollId,StudentEmail,ClassId,Status) VALUES (:name,:roll,:email,:class_id,1)');
            $q->execute([':name'=>$name,':roll'=>$roll,':email'=>$email,':class_id'=>$classId]);$savedId=(int)$dbh->lastInsertId();
        }
        respond(['message'=>'Student saved successfully.','student'=>getStudent($dbh,$savedId)]);
    }
    if ($action === 'delete_student') {
        $id=(int)($input['id']??0);if($id<1)respond(['error'=>'Invalid student id.'],422);
        $dbh->beginTransaction();$dbh->prepare('DELETE FROM tblresult WHERE StudentId=:id')->execute([':id'=>$id]);$q=$dbh->prepare('DELETE FROM tblstudents WHERE StudentId=:id');$q->execute([':id'=>$id]);$dbh->commit();
        respond(['message'=>'Student and related results deleted.']);
    }
    if ($action === 'save_result') {
        $studentId=(int)($input['student_id']??0);$entries=$input['marks']??[];$s=getStudent($dbh,$studentId);if(!$s)respond(['error'=>'Student not found.'],404);
        if(!is_array($entries)||!count($entries))respond(['error'=>'No subjects were supplied for this class.'],422);
        $subjects=subjectsForClass($dbh,(int)$s['class_id']);$allowed=[];foreach($subjects as $sub)$allowed[(string)$sub['subject_id']]=true;
        $seen=[];$valid=[];foreach($entries as $entry){$sid=(int)($entry['subject_id']??0);$mark=$entry['marks']??null;if(!isset($allowed[(string)$sid])||isset($seen[(string)$sid])||!is_numeric($mark)||(int)$mark<0||(int)$mark>100||((string)(int)$mark!==(string)$mark && !is_int($mark)))respond(['error'=>'Check each subject and enter whole-number marks from 0 to 100.'],422);$seen[(string)$sid]=true;$valid[]=['subject_id'=>$sid,'marks'=>(int)$mark];}
        if(count($valid)!==count($subjects))respond(['error'=>'Enter marks for every subject assigned to this class.'],422);
        $dbh->beginTransaction();$dbh->prepare('DELETE FROM tblresult WHERE StudentId=:sid AND ClassId=:cid')->execute([':sid'=>$studentId,':cid'=>$s['class_id']]);
        $insert=$dbh->prepare('INSERT INTO tblresult (StudentId,ClassId,SubjectId,marks,UpdationDate) VALUES (:sid,:cid,:sub,:marks,NOW())');foreach($valid as $v)$insert->execute([':sid'=>$studentId,':cid'=>$s['class_id'],':sub'=>$v['subject_id'],':marks'=>$v['marks']]);$dbh->commit();
        respond(['message'=>'Result saved successfully.','student'=>getStudent($dbh,$studentId)]);
    }
    respond(['error'=>'Unknown action.'],404);
} catch (InvalidArgumentException $e) { if($dbh->inTransaction())$dbh->rollBack(); respond(['error'=>$e->getMessage()],422); }
  catch (Throwable $e) { if($dbh->inTransaction())$dbh->rollBack(); error_log('SRMS modern API: '.$e->getMessage()); respond(['error'=>'A server error occurred. Check the PHP server log and database setup.'],500); }
