<?php

$tr = [
  '_' => 'confirm',
  '_todo_level' => 0,
  '_last_author' => 'Nael Sayegh',
  '_last_modif' => 1751647840,
  'mail_info_subject' => 'Your member information',
  'mail_info_body_html' => <<<HTML
      <h2>Hello {{username}} and welcome to the {{site}} community</h2>
      Your information is as follows:</p>
      <ul>
      <li>Username: {{username}}</li>
      <li>Email address: {{email}}</li>
      <li>Membership number: M{{id}}</li>
      <li>Signup date: {{signup_date}}</li>
      </ul>
    HTML,
  'mail_info_body_text' => <<<TEXT
      Hello {{username}} and welcome to the {{site}} community

      Your information is as follows:
      - Username: {{username}}
      - Email address: {{email}}
      - Membership number: M{{id}}
      - Signup date: {{signup_date}}
    TEXT,
];
