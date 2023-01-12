<?php 
  require_once('../config.php');
  global $DB;
  require_once('./curl.php');
  require_once ('./MoodleAPI.php');
  $domain = 'https://localhost/moodle38';
  $apiuser = 'apiuser';
  $apipwd = 'APIUser@1234';
  $rand = date('YmdHis');//== Random number for new user/courses  
  $mapi = new MoodleAPI($domain);
  $mapi->login($apiuser,$apipwd);
  $token = $mapi->getToken();
  echo 'Token   ' . $token.'<br />';
  $fullname = "t".$rand;
  $course = $mapi->updateCourse( array(
            'id' => '2',
            'fullname' => $fullname
          ));

  //upload scorm
  $filename = 'ks.zip';
  $filepath = 'C:/Users/Saumya/Downloads/';
  $filecontents = file_get_contents($filepath);
  $file_data = array('file_name' => $filename, 'file_content' => base64_encode($filecontents));

  $params = array(
    'contextid' => 25 ,      
    'component' => 'svasu',      
    'filearea' => 'package' ,       
    'itemid' => 65029118  ,  
    'filepath' => $filepath  ,     
    'filename' => $filename , 
    'filecontent ' => $filecontents,
    'contextlevel' => 'module',
    'instanceid ' => 9
  ); 

  $uploads = $mapi->uploadScorm($params);
  var_dump($uploads);

  $paramscourse = array(
    'courseid' => 2
  );
  $coursecontents = $mapi->getCourseContents($paramscourse);
  echo '<pre>';
  var_dump($coursecontents);
?>