<?php

$tr = [
  '_' => 'fg_passwd',
  '_todo_level' => 0,
  '_last_author' => 'Nael Sayegh',
  '_last_modif' => 1751647840,
  'title' => 'Forgot password',
  'mail_sent' => 'Check your emails, a reset link has been sent to you',
  'invalid_or_expired' => 'This token is invalid or has expired. Please try again.',
  'pwd_mismatch' => 'The two entered passwords do not match',
  'pwd_too_short' => 'Your password must contain at least 8 characters',
  'pwd_no_reuse' => 'The new password must be different from the last one used',
  'intro_text' => 'Please fill out the form below to request a password reset for {{site}}',
  'choose_new_pwd' => 'Please fill out the form below to choose your new password',
  'login_field' => 'Username or email address',
  'request_btn' => 'Reset',
  'new_password' => 'New password&nbsp;:',
  'confirm_password' => 'New password (Verification)&nbsp;:',
  'gen-psw' => 'Generate a password',
  'reset_btn' => 'Confirm',
  'js-to-gen' => 'Enable JavaScript if you wish to generate a password via the site',
  'mail_reset_subject' => 'Password Reset',
  'mail_reset_body_html' => <<<HTML
      <p>Hello {{username}},<br>
      Click on this link, valid for 1 hour, to choose your new password:&nbsp;<br>
      <a href="{{link}}">Choose my password</a>.</p>
      HTML,
  'mail_reset_body_text' => <<<TEXT
      Hello {{username}},
      Click on this link, valid for 1 hour, to choose your new password:
      {{link}}
    TEXT,
];
