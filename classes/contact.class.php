<?php

/**
 * Author: wbolt team
 * Author URI: https://www.wbolt.com
 */


class  OCW_Contact extends OCW_Base
{


    public static function init()
    {

        add_action('ocw_new_concat', array(__CLASS__, 'wb_new_concat'));

        if (is_admin()) {
            add_action('admin_menu', [__CLASS__, 'admin_menu']);

            add_action('wp_ajax_ocw_contact', array(__CLASS__, 'wp_ajax_ocw_contact'));
        }
    }

    public static function contact()
    {

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
    }

    public static function admin_menu()
    {
        // global $wpdb;
        $db = self::db();
        $t = $db->prefix . 'ocw_contact';
        $val = $db->get_var("select count(1) from $t where status=1 and is_new=1");

        $tips = '';
        if ($val) {
            $tips = '<span class="awaiting-mod count-' . $val . '"><span class="pending-count" aria-hidden="true">' . $val . '</span></span>';
        }
        add_submenu_page(
            OCW_Admin::$name,
            '多合一在线客服插件',
            '工单管理' . $tips,
            'administrator',
            OCW_Admin::$name . '#/wo-list',
            array(__CLASS__, 'render_views')
        );
    }

    public static function wb_new_concat($pid)
    {
        // global $wpdb;

        $conf = self::conf();

        if (!$conf['auto_reply_on']) {
            return;
        }

        $db = self::db();
        $msg = $conf['auto_reply_msg'] ? $conf['auto_reply_msg'] : $conf['auto_reply_default'];
        $t_detail = $db->prefix . 'ocw_contact_content';
        $d = array(
            'pid' => $pid,
            'content' => $msg,
            'pics' => '',
            'ip' => '0.0.0.0',
            'create_date' => current_time('mysql'),
            'uid' => 0,
        );
        $db->insert($t_detail, $d);
    }


    public static function wp_ajax_ocw_contact()
    {
        if (!current_user_can('manage_options')) {
            //exit();
            return;
        }
        if (!is_user_logged_in()) {
            return;
        }
        //ini_set('display_errors',true);
        $op = sanitize_text_field(self::param('op'));
        if (!$op) {
            return;
        }
        $allow = [
            'set_close',
            'delete',
            'processed',
            'reply',
            'get_cnf',
            'get_list',
            'get_detail'
        ];
        if (!in_array($op, $allow, true)) {
            return;
        }
        if (!wp_verify_nonce(self::param('_ajax_nonce'), 'wb_ocw_admin_ajax')) {
            return;
        }

        switch ($op) {

            case 'set_close':
                self::set_close();
                break;

            case 'delete':
                self::delete();
                break;

            case 'processed':
                self::set_processed();
                break;

            case 'reply':
                self::ask_reply();
                break;

            case 'get_cnf':
                $ret = array('code' => 0, 'desc' => 'success');
                $ret['data'] = self::conf();

                header('content-type:text/json;');
                echo wp_json_encode($ret);
                break;

            case 'get_list':
                self::get_contact_list();
                break;

            case 'get_detail':
                $ret = array('code' => 0, 'list' => array(), 'row' => array(), 'desc' => 'success');

                $id = absint(self::param('id', 0));
                if ($id) {
                    $data = self::get_detail($id, true);
                    // $row = $data['row'];
                    // $type = $row->type;
                    // $row->type = OCW_Admin::opt('items_data.msg.subject_type')[$type];

                    $ret['list'] = $data['list'];
                    $ret['row'] = $data['row'];
                }

                $ret['cnf'] = self::conf();
                self::ajax_resp($ret);
                break;
        }
        exit();
    }

    public static function delete()
    {
        // global $wpdb;

        $id = absint(self::param('id', 0));
        if (!$id) {
            return false;
        }

        $db = self::db();
        $t = $db->prefix . 'ocw_contact';
        $db->delete($t, array('id' => $id));
        $db->delete($t . '_content', array('pid' => $id));
        return true;
    }

    public static function set_close()
    {
        // global $wpdb;

        $pid = absint(self::param('id', 0));
        if (!$pid) {
            return;
        }
        $db = self::db();
        $t = $db->prefix . 'ocw_contact';
        $t_detail = $db->prefix . 'ocw_contact_content';
        $user = wp_get_current_user();
        $d = array(
            'pid' => $pid,
            'content' => '关闭工单',
            'pics' => '',
            'ip' => $_SERVER['REMOTE_ADDR'],
            'create_date' => current_time('mysql'),
            'uid' => $user->ID,
        );

        $ret = $db->insert($t_detail, $d);

        $db->update($t, array('update_time' => current_time('mysql'), 'is_read' => 1, 'is_new' => 0, 'status' => 2), array('id' => $pid));
    }

    public static function set_processed()
    {
        // global $wpdb;

        $pid = absint(self::param('id', 0));
        if (!$pid) {
            return;
        }
        $db = self::db();
        $t = $db->prefix . 'ocw_contact';
        $db->update($t, array('update_time' => current_time('mysql'), 'is_read' => 1, 'is_new' => 0, 'status' => 3), array('id' => $pid));
    }

    public static function ask_reply()
    {
        // global $wpdb;

        $db = self::db();
        $user = wp_get_current_user();
        $t = $db->prefix . 'ocw_contact';
        $t_detail = $db->prefix . 'ocw_contact_content';

        $pid = absint(self::param('id', 0));
        $content = sanitize_textarea_field(self::param('content'));
        $pics = self::param('pics');
        $s_pics = array();
        if ($pics) {

            if (is_array($pics)) {
                $s_pics = $pics;
            } else {
                $s_pics = explode(',', $pics);
            }
        }

        $pics = $s_pics;


        $d = array(
            'pid' => $pid,
            'content' => substr($content, 0, 1000),
            'pics' => $pics ? wp_json_encode($pics) : '',
            'ip' => $_SERVER['REMOTE_ADDR'],
            'create_date' => current_time('mysql'),
            'uid' => $user->ID,
        );

        $db->insert($t_detail, $d);

        $d['cid'] = $db->insert_id;

        if ($d['cid']) {
            $db->update($t, array('update_time' => current_time('mysql'), 'is_read' => 1, 'is_new' => 0), array('id' => $pid));
        }


        do_action('ocw_contact_reply', $pid, $d);

        self::ajax_resp($d);
        return $d;
    }

    static function wb_is_administrator($user_id)
    {
        if (!$user_id) {
            return 1;
        }
        if ($user_id == -1) {
            return 0; //未登陆用户
        }

        $user = get_userdata($user_id);
        if (!empty($user->roles) && in_array('administrator', $user->roles)) {
            return 1;  // 是管理员
        } else {
            return 0;  // 非管理员
        }
    }

    /**
     * 工单列表
     */
    public static function get_contact_list()
    {

        if (!current_user_can('manage_options')) {
            $ret = array('code' => 0, 'desc' => '403', 'total' => 0, 'data' => [], 'num' => 0);
            header('content-type:text/json;charset=utf-8');
            echo wp_json_encode($ret);
            exit();
        }

        /// $param = ['pagesize' => 5];
        //		$cur_page_url = admin_url().'admin.php?page='.$_REQUEST['page'];

        // global $wpdb;

        $get = $_POST;
        foreach ($get as $k => $v) {
            if (is_string($v)) $get[$k] = sanitize_text_field($v);
        }
        $num = 30;
        if (isset($get['num']) && $get['num']) {
            $num = intval($get['num']);
        }
        if ($num < 1) {
            $num = 30;
        }
        $page = 1;
        if (isset($get['page']) && $get['page']) {
            $page = intval($get['page']);
        }
        if ($page < 1) {
            $page = 1;
        }

        $limit = " LIMIT " . (($page - 1) * $num) . ", $num";

        $db = self::db();
        $t = $db->prefix . 'ocw_contact';

        //`uid`, `expired`, `status`, `blance`, `consume`
        $unlogin_user_label = __('网友', 'wb-ocw');
        $sql = "SELECT SQL_CALC_FOUND_ROWS a.*,IFNULL(c.display_name,'" . $unlogin_user_label . "') display_name,IFNULL(c.user_login,'" . $unlogin_user_label . "') user_login FROM $t a  LEFT JOIN $db->users c ON a.uid=c.ID WHERE a.status<9 ";

        if (isset($get['fromdate']) && $get['fromdate']) {
            $sql .= $db->prepare(" AND a.create_date >=%s", $get['fromdate'] . ' 00:00:00');
        }
        if (isset($get['todate']) && $get['todate']) {
            $sql .= $db->prepare(" AND a.create_date<=%s", $get['todate'] . ' 23:59:59');
        }
        if (isset($get['is_new']) && $get['is_new']) {
            $sql .= $db->prepare(" AND a.is_new = %d", ($get['is_new'] - 1));
        }

        if (isset($get['type']) && $get['type'] > -1) {

            $sql .= $db->prepare(" AND a.type = %s", $get['type']);
        }

        if (isset($get['status']) && $get['status']) {
            $sql .= $db->prepare(" AND a.status = %d", $get['status']);
        }

        if (isset($get['q']) && $get['q']) {
            $q = trim($get['q']);
            $sql .= $db->prepare(" AND concat_ws('',c.user_login,c.user_email,c.display_name,a.title,a.name,a.email,a.sn) like %s", '%' . $q . '%');
        }

        $sort_by = 'a.update_time';
        if (isset($get['orderby']) && in_array($get['orderby'], ['create_date', 'update_time'])) {
            $sort_by = ' a.' . $get['orderby'];
        }
        if (isset($get['order']) && in_array($get['order'], ['desc', 'asc'])) {
            $sort_by .= ' ' . strtoupper($get['order']);
        } else {
            $sort_by .= ' DESC';
        }
        $sql .= " ORDER BY " . $sort_by . ' ' . $limit;

        $list = $db->get_results($sql);
        $total = $db->get_var("SELECT FOUND_ROWS()");

        foreach ($list as $item) {
            $item->last_update_user = self::last_name($item->id);
            $item->msg = self::get_detail($item->id);
        }

        $ret = array('code' => 0, 'desc' => 'success');

        $ret['total'] = intval($total);
        $ret['num'] = $num;
        $ret['data'] = $list;

        header('content-type:text/json;charset=utf-8');
        echo wp_json_encode($ret);
        exit();
    }


    /**
     * 工单详情
     */
    public static function get_detail($id, $get_row = false)
    {

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $db = self::db();
        // global $wpdb;
        $t = $db->prefix . 'ocw_contact';
        $t_detail = $db->prefix . 'ocw_contact_content';


        $row = $db->get_row($db->prepare("SELECT a.* FROM $t a  WHERE a.id=%d", $id));


        $sql = $db->prepare("SELECT a.content,IFNULL(b.display_name,'system') display_name from $t_detail a LEFT JOIN $db->users b ON a.uid=b.ID WHERE a.pid=%d ORDER BY a.cid ASC ", $id);
        //echo $sql;
        $list = $db->get_results($sql);

        if ($get_row) {
            return array(
                'row' => $row,
                'list' => $list
            );
        }

        return $list;
    }

    public static function avatar_url($uid)
    {
        static $src_list = array();
        $src = wb_assets_url('img') . '/images/def_avatar.png';

        if (!$uid) {
            return $src;
        }
        if (isset($src_list[$uid])) {
            return $src_list[$uid];
        }


        $img_html = get_avatar($uid, 96, $src);

        if (preg_match('#src=([^\s]+)#i', $img_html, $match)) {
            $src = trim($match[1], "\"'");
        }
        $src_list[$uid] = $src;
        return $src;
    }

    public static function auto_close()
    {
        // global $wpdb;
        $db = self::db();
        $t = $db->prefix . 'ocw_contact_content';
        $t2 = $db->prefix . 'ocw_contact';

        $cap_key = $db->prefix . 'capabilities';

        $col = $db->get_col("SELECT user_id FROM $db->usermeta WHERE meta_key='$cap_key' AND meta_value REGEXP 'administrator'");

        if (empty($col)) {
            return;
        }

        $sql = "SELECT MAX(cid) AS cid FROM $t a,$t2 b WHERE a.pid=b.id AND b.status=1 GROUP BY a.pid";

        $uid = implode(',', $col);
        $list = $db->get_results("SELECT * FROM $t WHERE uid IN($uid) AND DATEDIFF(NOW(),create_date) > 7 AND cid IN($sql) ");

        if ($list) foreach ($list as $r) {
            $d = array(
                'pid' => $r->pid,
                'content' => __('您的工单长时间未反馈信息，系统自动关闭此工单，如需继续联系，请重新发起工单。', 'wb-ocw'),
                'pics' => '',
                'ip' => '0.0.0.0',
                'create_date' => current_time('mysql'),
                'uid' => 0,
            );

            $ret = $db->insert($t, $d);

            if ($ret) {

                $db->update($t2, array('update_time' => current_time('mysql'), 'is_read' => 1, 'is_new' => 0, 'status' => 2), array('id' => $r->pid));
            }
        }
    }

    public static function last_name($pid)
    {
        // global $wpdb;


        $db = self::db();
        $t = $db->prefix . 'ocw_contact_content';

        $row = $db->get_row($db->prepare("SELECT a.*,b.display_name FROM $t a LEFT  JOIN $db->users b ON a.uid=b.ID WHERE  a.pid=%d ORDER BY a.cid DESC LIMIT 1", $pid));

        if ($row && $row->display_name) {
            return $row->display_name;
        }

        return __('未登录访客', 'wb-ocw');
    }

    /**
     * 获取设置值
     */
    public static function conf()
    {
        return OCW_Admin::opt('items_data.msg.subject_type');
    }


    public static function limit($pagesize)
    {
        $paged = absint(self::param('paged', 1));
        if (!$paged) {
            $paged = 1;
        }
        $_POST['paged'] = $paged;

        $pagesize = $pagesize ? abs($pagesize) : 10;

        return 'LIMIT ' . (($paged - 1) * $pagesize) . ',' . $pagesize;
    }
}
