<?php
/**
 * Box REST Client Library for PHP5 Developers
 *
 *
 * @author James Levy <james@box.net>
 * @link http://enabled.box.net
 * @access public
 * @version 1.0
 * copyright Box.net 2007
 * Available for use and distribution under GPL-license
 * Go to http://www.gnu.org/licenses/gpl-3.0.txt for full text
 */

/**
 * Modified by Dongsheng Cai <dongsheng@cvs.moodle.org>
 *
 */

class boxclient {
    public $auth_token = '';

    private $_box_api_url = 'http://www.box.net/api/1.0/rest';
    private $_box_api_upload_url = 'http://upload.box.net/api/1.0/upload';
    private $_error_code = '';
    private $_error_msg = '';

    public function __construct($api_key, $auth_token = '') {
        $this->api_key    = $api_key;
        $this->auth_token = $auth_token;
    }
    // Setup for Functions
    function makeRequest($method, $params = array()) {
        $this->_clearErrors();
        $c = new curl(array('cache'=>true));
        if ($method == 'upload'){
            $request = $this->_box_api_upload_url.'/'.
                $this->auth_token.'/'.$params['folder_id'];
            $xml = $c->post($request, params);
        }else{
            $args = array();
            $xml = $c->get($this->_box_api_url, $params);
        }
        $xml_parser = xml_parser_create();
        xml_parse_into_struct($xml_parser, $xml, $data);
        xml_parser_free($xml_parser);
        return $data;
    }
    function getTicket($params = array()) {
        $params['api_key'] = $this->api_key;
        $params['action']  = 'get_ticket';
        $ret_array = array();
        $data = $this->makeRequest('action=get_ticket', $params);
        if ($this->_checkForError($data)) {
            return false;
        }
        foreach ($data as $a) {
            switch ($a['tag']) {
            case 'STATUS':
                $ret_array['status'] = $a['value'];
                break;
            case 'TICKET':
                $ret_array['ticket'] = $a['value'];
                break;
            }
        }
        return $ret_array;
    }

    // $options['username'] and $options['password'] must be
    // given, we  will use them to obtain a valid auth_token
    // To get a token, you should use following code:
    //
    // $box = new boxclient('dmls97d8j3i9tn7av8y71m9eb55vrtj4');
    // Get a ticket
    // $t = $box->getTicket();
    // $box->getAuthToken($t['ticket'], array(
    //              'username'=>'dongsheng@moodle.com',
    //              'password'=>'xxx'));
    //
    function getAuthToken($ticket, $username, $password) {
        $c = new curl;
        $c->setopt(array('CURLOPT_FOLLOWLOCATION'=>0));
        $param =  array(
            'login_form1'=>'',
            'login'=>$username,
            'password'=>$password,
            'dologin'=>1,
            '__login'=>1
            );
        $ret = $c->post('http://www.box.net/api/1.0/auth/'.$ticket, $param);
        $header = $c->getResponse();
        $location = $header['location'];
        preg_match('#auth_token=(.*)$#i', $location, $matches);
        $auth_token = $matches[1];
        if(!empty($auth_token)) {
            $this->auth_token = $auth_token;
            return $auth_token;
        } else {
            return false;
        }
    }

    // Get the file list
    function getAccountTree($params = array()) {
        $params['auth_token'] = $this->auth_token;
        $params['folder_id']  = 0;
        $params['api_key']    = $this->api_key;
        $params['action']     = 'get_account_tree';
        $params['onelevel']   = 1;
        $params['params[]']   = 'nozip';
        $ret_array = array();
        $data = $this->makeRequest('action=get_account_tree', $params);
        if ($this->_checkForError($data)) {
            return false;
        }
        $tree_count=count($data);
        global $tree_count;
        $entry_count = 0;
        for ($i=0, $tree_count=count($data); $i<$tree_count; $i++) {
            $a = $data[$i];
            switch ($a['tag'])
            {
            case 'FOLDER':
                if (@is_array($a['attributes'])) {
                    $ret_array['folder_id'][$i] = $a['attributes']['ID'];
                    $ret_array['folder_name'][$i] = $a['attributes']['NAME'];
                    $ret_array['shared'][$i] = $a['attributes']['SHARED'];
                }
                break;

            case 'FILE':
                if (@is_array($a['attributes'])) {
                    $ret_array['file_id'][$i] = $a['attributes']['ID'];
                    @$ret_array['file_name'][$i] = $a['attributes']['FILE_NAME'];
                    @$ret_array['file_keyword'][$i] = $a['attributes']['KEYWORD'];
                    $entry_count++;
                }
                break;
            }
        }
        return $ret_array;
    }

    // Create New Folder
    function CreateFolder($new_folder_name, $params = array()) {
        $params['auth_token'] =  $this->auth_token;
        $params['api_key']    = $this->api_key;
        $params['action']     = 'create_folder';
        //Set to '0' by default. Change to create within sub-folder.
        $params['parent_id']  = 0; 
        $params['name']       = $new_folder_name;
        //Set to '1' by default. Set to '0' to make folder private.
        $params['share']      = 1; 

        $ret_array = array();
        $data = $this->makeRequest('action=create_folder', $params);


        if ($this->_checkForError($data)) {
            return false;
        }
        foreach ($data as $a) {
            switch ($a['tag']) {
            case 'FOLDER_ID':
                $ret_array['folder_id'] = $a['value'];
                break;

            case 'FOLDER_NAME':
                $ret_array['folder_type'] = $a['value'];
                break;

            case 'SHARED':
                $ret_array['shared'] = $a['value'];
                break;
            case 'PASSWORD':
                $ret_array['password'] = $a['value'];
                break;
            }
        }
        return $ret_array;
    }
    // Upload File
    function UploadFile ($params = array()) {
        $params['auth_token'] = $this->auth_token;
        // this param should be the full path of the file
        $params['new_file1']  = '@'.$params['file'];
        $params['folder_id']  = 0; //Set to '0' by default. Change to create within sub-folder.
        $params['share']      = 1; //Set to '1' by default. Set to '0' to make folder private.
        $ret_array = array();
        $data = $this->makeRequest('upload', $params);
        if ($this->_checkForError($data)) {
            return false;
        }
        for ($i=0, $tree_count=count($data); $i<$tree_count; $i++) {
            $a = $data[$i];
            switch ($a['tag']) {
            case 'STATUS':
                $ret_array['status'] = $a['value'];
                break;

            case 'FILE':
                if (is_array($a['attributes'])) {
                    $ret_array['file_name'][$i] = $a['attributes']['FILE_NAME'];
                    $ret_array['id'][$i] = $a['attributes']['ID'];
                    $ret_array['folder_name'][$i] = $a['attributes']['FOLDER_NAME'];
                    $ret_array['error'][$i] = $a['attributes']['ERROR'];
                    $ret_array['public_name'][$i] = $a['attributes']['PUBLIC_NAME'];
                    $entry_count++;
                }
                break;
            }
        }

        return $ret_array;
    }

    // Register New User
    function RegisterUser($params = array()) {
        $params['api_key'] = $this->api_key;
        $params['action']  = 'register_new_user';
        $params['login']   = $_REQUEST['login'];
        $params['password'] = $_REQUEST['password'];
        $ret_array = array();
        $data = $this->makeRequest('action=register_new_user', $params);
        if ($this->_checkForError($data)) {
            return false;
        }
        foreach ($data as $a) {
            switch ($a['tag']) {
            case 'STATUS':
                $ret_array['status'] = $a['value'];
                break;

            case 'AUTH_TOKEN':
                $ret_array['auth_token'] = $a['value'];
                break;

            case 'LOGIN':
                $ret_array['login'] = $a['value'];
                break;
            case 'SPACE_AMOUNT':
                $ret_array['space_amount'] = $a['value'];
                break;
            case 'SPACE_USED':
                $ret_array['space_used'] = $a['value'];
                break;
            }
        }

        return $ret_array;
    }

    // Add Tags  (http://enabled.box.net/docs/rest#add_to_tag)

    function AddTag($tag, $id, $target_type, $params = array()) {
        $params['auth_token'] = $this->auth_token;
        $params['api_key']    = $this->api_key;
        $params['action']     = 'add_to_tag';
        $params['target']     = $target_type; // File or folder
        $params['target_id']  = $id; // Set to ID of file or folder
        $params['tags[]']     = $tag;
        $ret_array = array();
        $data = $this->makeRequest('action=add_to_tag', $params);
        if ($this->_checkForError($data)) {
            return false;
        }
        foreach ($data as $a) {
            switch ($a['tag']) {
            case 'STATUS':
                $ret_array['status'] = $a['value'];

                break;
            }
        }
        return $ret_array;
    }

    // Public Share  (http://enabled.box.net/docs/rest#public_share)
    function PublicShare($message, $emails, $id, $target_type, $password, $params = array()) {
        $params['auth_token'] = $this->auth_token;
        $params['api_key']    = $this->api_key;
        $params['action']     = 'public_share';
        $params['target']     = $target_type;
        $params['target_id']  = $id;
        $params['password']   =  $password;
        $params['message']    = $message;
        $params['emails']     = $emails;
        $ret_array = array();
        $data = $this->makeRequest('action=public_share', $params);
        if ($this->_checkForError($data)) {
            return false;
        }
        foreach ($data as $a) {
            switch ($a['tag']) {
            case 'STATUS':
                $ret_array['status'] = $a['value'];
                break;
            case 'PUBLIC_NAME':
                $ret_array['public_name'] = $a['value'];
                break;
            }
        }

        return $ret_array;
    }
    // Get Friends  (http://enabled.box.net/docs/rest#get_friends)
    function GetFriends ($params = array()) {
        $params['auth_token'] = $this->auth_token;
        $params['action']     = 'get_friends';
        $params['api_key']    = $this->api_key;
        $params['params[]']   = 'nozip';
        $ret_array = array();
        $data = $this->makeRequest('action=get_friends', $params);
        if ($this->_checkForError($data)) {
            return false;
        }
        foreach ($data as $a) {
            switch ($a['tag']) {
            case 'NAME':
                $ret_array['name'] = $a['value'];
                break;
            case 'EMAIL':
                $ret_array['email'] = $a['value'];
                break;
            case 'ACCEPTED':
                $ret_array['accepted'] = $a['value'];
                break;
            case 'AVATAR_URL':
                $ret_array['avatar_url'] = $a['value'];
                break;
            case 'ID':
                $ret_array['id'] = $a['value'];
                break;
            case 'URL':
                $ret_array['url'] = $a['value'];
                break;
            case 'STATUS':
                $ret_array['status'] = $a['value'];
                break;
            }
        }
        return $ret_array;
    }

    // Logout User
    function Logout($params = array()) {
        $params['auth_token'] = $this->auth_token;
        $params['api_key']    = $this->api_key;
        $params['action']     = 'logout';
        $ret_array = array();
        $data = $this->makeRequest('action=logout', $params);
        if ($this->_checkForError($data)) {
            return false;
        }
        foreach ($data as $a) {
            switch ($a['tag']) {
            case 'ACTION':
                $ret_array['logout'] = $a['value'];

                break;
            }
            return $ret_array;
        }
    }
    function _checkForError($data) {
        if (@$data[0]['attributes']['STAT'] == 'fail') {
            $this->_error_code = $data[1]['attributes']['CODE'];
            $this->_error_msg = $data[1]['attributes']['MSG'];
            return true;
        }
        return false;
    }

    public function isError() {
        if  ($this->_error_msg != '') {
            return true;
        }
        return false;
    }

    function getErrorMsg() {
        return '<p>Error: (' . $this->_error_code . ') ' . $this->_error_msg . '</p>';
    }

    function getErrorCode() {
        return $this->_error_code;
    }

    function _clearErrors() {
        $this->_error_code = '';
        $this->_error_msg = '';
    }

}
?>
