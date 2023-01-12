
<?php 
/*$token = '9740ce3f922f3b4769e26667507f0738';
$serverurl = 'https://localhost/moodle38/webservice/rest/server.php';

$filepath = 'http://demo.knowledgesynonyms.com/sco.zip';

$filecontent = base64_encode(file_get_contents($filepath));
$params = array(
    'filecontent' => $filecontent,
    'filename' => 'sco.zip',
    'contextid' => 25, // The context ID of the course
    'component' => 'course',
    'filearea' => 'legacy',
    'itemid' => 0,
    'filepath' => '/',
    'license' => 'allrightsreserved',
);

$curl = curl_init($serverurl . '?wstoken=' . $token . '&wsfunction=core_files_upload');
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($curl);
curl_close($curl);
var_dump($curl);
$response = json_decode($response);
var_dump($response);
if (isset($response->error)) {
    // Handle error
} else {
    $fileid = $response->id;
    // Update the course with the new file ID
    $courseupdateparams = array(
        'id' => 123, // The course ID
        'files_filemanager' => $fileid,
    );
    $curl = curl_init($serverurl . '?wstoken=' . $token . '&wsfunction=core_course_update_courses');
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $courseupdateparams);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($curl);
    curl_close($curl);
}*/
/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$service_url = 'https://localhost/moodle38/webservice/rest/server.php';
$curl = curl_init($service_url);
$curl_post_data = array(
'wstoken' => '9740ce3f922f3b4769e26667507f0738',
'wsfunction' => 'core_webservice_get_site_info',
);
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, $curl_post_data);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$curl_response = curl_exec($curl);
var_dump($curl_response);*/
require_once($CFG->dirroot . "/webservice/lib.php");
$webservicemanager = new webservice();
