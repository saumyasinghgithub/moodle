<?php
require_once('curl.php');


class MoodleAPI extends MoodleAPIBase{

    public function login($username,$password){        
        $url = $this->domain.'/login/token.php?service=moodle_api';      
        $fields = (object)array('username' => $username, 'password' => $password);
        $curl = new curl;
        $resp = $curl->post($url, $fields);
        $resp = json_decode($resp);             
        if($resp->token!=null){
            $this->setToken($resp->token);
        }else{
            die('Invalid Login');
        }
    }

    public function updateCourse($params){
        $params = array((object)$params);
        $newparams = array('courses' => $params);
        return $this->hit('core_course_update_courses',$newparams);
    }

    public function uploadScorm($params){
        $params = array((object)$params);
        return $this->hit('core_files_upload',$params);
    }

    public function getCourseContents($paramscourse){
        return $this->hit('core_course_get_contents',$paramscourse);
    }
}

//=== Base class


class MoodleAPIBase{

    protected $token;
    protected $api;
    protected $domain;

    public function __construct($domain){
        $this->domain = $domain;        
        $this->api = $domain.'/webservice/rest/server.php';
    }

    public function setToken($token){
        $this->token = $token;
    }

    public function getToken(){
        return $this->token;
    }

    public function hit($func,$params){

        if(!$this->token)
        return false;

        /// REST CALL
        //header('Content-Type: text/plain');
        $serverurl = $this->api;
        $serverurl .= '?moodlewsrestformat=json';
        $serverurl .= '&wstoken=' . $this->token;
        $serverurl .= '&wsfunction='.$func;
        $curl = new curl;
        $resp = $curl->post($serverurl, $params);
        return json_decode($resp);
    }

}


