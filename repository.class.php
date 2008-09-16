<?php
/**
 * repository_boxnet class
 * This is a subclass of repository class
 *
 * @author Dongsheng Cai
 * @version $Id$
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

require_once($CFG->libdir.'/boxlib.php');

/**
 *
 */
class repository_boxnet extends repository {
    private $box;

    /**
     *
     * @global <type> $SESSION
     * @param <type> $repositoryid
     * @param <type> $context
     * @param <type> $options
     */
    public function __construct($repositoryid, $context = SITEID, $options = array()) {
        global $SESSION;

        $options['username']   = optional_param('boxusername', '', PARAM_RAW);
        $options['password']   = optional_param('boxpassword', '', PARAM_RAW);
        $options['ticket']     = optional_param('ticket', '', PARAM_RAW);
        $reset                 = optional_param('reset', 0, PARAM_INT);
        parent::__construct($repositoryid, $context, $options);
        $this->api_key = $this->get_option('api_key');
        if (empty($this->api_key)) {
        }
        $sess_name = 'box_token'.$this->id;
        $this->sess_name = 'box_token'.$this->id;
        // do login
        if (!empty($options['username']) && !empty($options['password']) && !empty($options['ticket']) ) {
            $this->box = new boxclient($this->api_key);
            try {
                $SESSION->$sess_name = $this->box->getAuthToken($options['ticket'], 
                $options['username'], $options['password']);
            } catch (repository_exception $e) {
                throw $e;
            }
        }
        // already logged
        if (!empty($SESSION->$sess_name)) {
            if (empty($this->box)) {
                $this->box = new boxclient($this->api_key, $SESSION->$sess_name);
            }
            $this->auth_token = $SESSION->$sess_name;
        } else {
            $this->box = new boxclient($this->api_key);
        }
    }

    /**
     *
     * @global <type> $SESSION
     * @return <type>
     */
    public function check_login() {
        global $SESSION;

        return !empty($SESSION->{$this->sess_name});
    }

    /**
     *
     * @global <type> $SESSION
     * @return <type>
     */
    public function logout() {
        global $SESSION;

        unset($SESSION->{$this->sess_name});
        return $this->print_login();
    }

    /**
     *
     * @param <type> $options
     * @return <type>
     */
    public function set_option($options = array()) {
        if (!empty($options['api_key'])) {
            set_config('api_key', trim($options['api_key']), 'boxnet');
        }
        unset($options['api_key']);
        $ret = parent::set_option($options);
        return $ret;
    }

    /**
     *
     * @param <type> $config
     * @return <type>
     */
    public function get_option($config = '') {
        if ($config==='api_key') {
            return trim(get_config('boxnet', 'api_key'));
        } else {
            $options['api_key'] = trim(get_config('boxnet', 'api_key'));
        }
        $options = parent::get_option($config);
        return $options;
    }

    /**
     *
     * @global <type> $SESSION
     * @return <type>
     */
    public function global_search() {
        global $SESSION;
        $sess_name = 'box_token'.$this->id;
        if (empty($SESSION->$sess_name)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     *
     * @global <type> $DB
     * @return <type>
     */
    public function get_login() {
        global $DB;

        if ($entry = $DB->get_record('repository_instances', array('id'=>$this->id))) {
            $ret->username = $entry->username;
            $ret->password = $entry->password;
        } else {
            $ret->username = '';
            $ret->password = '';
        }
        return $ret;
    }

    /**
     *
     * @global <type> $CFG
     * @global <type> $SESSION
     * @param <type> $path
     * @param <type> $search
     * @return <type>
     */
    public function get_listing($path = '/', $search = '') {
        global $CFG, $SESSION;

        $list = array();
        $ret  = array();
        if (!empty($search)) {
            $tree = $this->box->getAccountTree();
            if (!empty($tree)) {
                $filenames = $tree['file_name'];
                $fileids   = $tree['file_id'];
                $filesizes = $tree['file_size'];
                $filedates = $tree['file_date'];
                $fileicon  = $tree['thumbnail'];
                foreach ($filenames as $n=>$v) {
                    if (strstr($v, $search) !== false) {
                        $list[] = array('title'=>$v, 
                                'size'=>$filesizes[$n],
                                'date'=>$filedates[$n],
                                'source'=>'http://box.net/api/1.0/download/'
                                    .$this->options['auth_token'].'/'.$fileids[$n],
                                'thumbnail'=>$CFG->pixpath.'/f/'.mimeinfo('icon', $v));
                    }
                }
            }
            $ret['list'] = $list;
            return $ret;
        }
        $tree = $this->box->getfiletree($path);
        if (!empty($tree)) {
            $ret['list']   = $tree;
            $ret['manage'] = 'http://www.box.net/files';
            $ret['path'] = array(array('name'=>'Root', 'path'=>0));
            $this->listing = $tree;
            return $ret;
        } else {
            $sess_name = 'box_token'.$this->id;
            unset($SESSION->$sess_name);
            throw new repository_exception('nullfilelist', 'repository_boxnet');
        }
    }

    /**
     *
     * @return <type>
     */
    public function print_login() {
        $t = $this->box->getTicket();
        $ret = $this->get_login();
        if ($this->options['ajax']) {
            $ticket_field->type = 'hidden';
            $ticket_field->name = 'ticket';
            $ticket_field->value = $t['ticket'];

            $user_field->label = get_string('username', 'repository_boxnet').': ';
            $user_field->id    = 'box_username';
            $user_field->type  = 'text';
            $user_field->name  = 'boxusername';
            $user_field->value = $ret->username;
            
            $passwd_field->label = get_string('password', 'repository_boxnet').': ';
            $passwd_field->id    = 'box_password';
            $passwd_field->type  = 'password';
            $passwd_field->name  = 'boxpassword';

            $ret = array();
            $ret['login'] = array($ticket_field, $user_field, $passwd_field);
            return $ret;
        }
    }

    /**
     *
     * @return <type>
     */
    public function print_search() {
        return false;
    }

    /**
     *
     * @return <type>
     */
    public static function has_admin_config() {
        return true;
    }

    /**
     *
     * @return <type>
     */
    public static function has_instance_config() {
        return false;
    }

    /**
     *
     * @return <type>
     */
    public static function has_multiple_instances() {
        return false;
    }

    /**
     *
     * @return <type>
     */
    public static function get_admin_option_names() {
        return array('api_key');
    }

    /**
     *
     * @return <type>
     */
    public static function get_instance_option_names() {
        return array('share_url');
    }

    /**
     *
     * @param <type> $
     */
    public function admin_config_form(&$mform) {
        $public_account = get_config('boxnet', 'public_account');
        $api_key = get_config('boxnet', 'api_key');
        if (empty($api_key)) {
            $api_key = '';
        }
        $strrequired = get_string('required');
        $mform->addElement('text', 'api_key', get_string('apikey', 'repository_boxnet'), array('value'=>$api_key,'size' => '40'));
        $mform->addRule('api_key', $strrequired, 'required', null, 'client');
    }

    /**
     *
     * @param <type> $ 
     */
    public function instance_config_form(&$mform) {
        //$share_url = get_config('boxnet', 'share_url');
        $mform->addElement('text', 'share_url', get_string('shareurl', 'repository_boxnet'));
    }
}

?>
