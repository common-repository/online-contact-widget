<?php if (!defined('ONLINE_CONTACT_WIDGET_PATH')) return; ?>
<form class="ocw-wb-form" id="J_OCWForm" method="post" autocomplete="off">
  <?php
  echo '<input type="hidden" name="_ajax_nonce" value="' . $ajax_nonce . '"/>';
  $user = wp_get_current_user();
  $uid = $user ? $user->ID : 0;
  $need_login = OCW_Admin::opt('items_data.msg.need_login');

  if ($need_login == '1' && !$uid) {
    $login_url = OCW_Admin::opt('login_url');

    if (!$login_url) {
      $login_url = wp_login_url();
    }
    $login_link_label = '<a class="ocw-link login" href="' . esc_url($login_url) . '" target="_blank">' . _x('立即登录', '在线工单, 登录链接label', 'wb-ocw') . '</a>';
  ?>

    <div class="ocw-panel-msg">
      <?php printf(_x('需登录后才可留言。<br>您尚未登录网站账户，%s。', '在线工单, 未登录时提示, %s 为登录链接', 'wb-ocw'), $login_link_label); ?>
    </div>
    <div class="cow-align-center">
      <a rel="nofollow" class="ocw-btn-cancel j-cancel-form"><?php _ex('取消', '按钮', 'wb-ocw'); ?></a>
    </div>
  <?php
  } else {
  ?>
    <div id="OCW_msg" class="ocw-msg-bar"></div>
    <input type="hidden" name="op" value="new">
    <div class="ocw-form-item">
      <input type="text" name="name" placeholder="<?php _ex('姓名', '表单字段名', 'wb-ocw'); ?>" value="" class="ocw-form-control required requiredField subject" />
    </div>
    <div class="ocw-form-item">
      <select class="ocw-dropdown block" name="type">
        <?php
        $types = $msg_opt['subject_type'];
        foreach ($types as $k => $type) :
          echo '<option value="' . $type . '">' . $type . '</option>';
        endforeach; ?>
      </select>
    </div>
    <div class="ocw-form-item with-dropdown-inline">
      <select class="ocw-dropdown" name="contact_type">
        <?php
        $ways = explode(',', $msg_opt['form_contact_ways']);
        // $way_cnf = $msg_cnf['form_contact_way'];
        $way_cnf = array(
          'email' => _x('邮箱', '联系方式', 'wb-ocw'),
          'qq' => _x('QQ', '联系方式', 'wb-ocw'),
          'wx' => _x('微信', '联系方式', 'wb-ocw'),
          'mobile' => _x('手机', '联系方式', 'wb-ocw'),
        );
        foreach ($ways as $k) :
          echo '<option value="' . $k . '">' . $way_cnf[$k] . '</option>';
        endforeach; ?>
      </select>
      <div class="wdi-main">
        <input type="text" name="contact" placeholder="<?php _ex('联系方式', '表单字段名', 'wb-ocw'); ?>" class="ocw-form-control required requiredField" />
      </div>
    </div>
    <div class="ocw-form-item">
      <textarea class="ocw-form-control" placeholder="<?php _ex('留言', '表单字段名', 'wb-ocw'); ?>" name="message"></textarea>
    </div>
    <?php
    $captcha = $msg_opt['captcha'];
    if ($captcha['type'] == 'base') :
      $captcha_image_url = admin_url('admin-ajax.php') . '?action=owc_recaptcha&op=captcha'
    ?>
      <div class="ocw-form-item ocw-form-captcha">
        <input class="ocw-form-control captcha-control" type="text" placeholder="<?php _ex('验证码', '表单字段名', 'wb-ocw'); ?>" name="ocw_captcha" autocomplete="off" maxlength="4" id="ocw_captcha" />
        <span class="ocw-captcha" title="<?php _ex('点击更换验证码', '表单字段名', 'wb-ocw'); ?>">
          <img src="<?php echo esc_url($captcha_image_url); ?>" class="captcha_img inline" />
        </span>
      </div>
    <?php endif; ?>

    <div class="ocw-btns">
      <button class="ocw-wb-btn ocw-btn-primary" type="button" id="OCW_submitBtn"><?php _ex('提交', '按钮', 'wb-ocw'); ?></button>
      <a rel="nofollow" class="ocw-btn-cancel j-cancel-form"><?php _ex('取消', '按钮', 'wb-ocw'); ?></a>
    </div>
  <?php } ?>
</form>