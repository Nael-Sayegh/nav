<?php

$tr = [
  '_' => 'confirm',
  '_todo_level' => 0,
  '_last_author' => 'Nael Sayegh',
  '_last_modif' => 1754926474,
  'mail_info_subject' => 'Vos informations de membre',
  'mail_info_body_html' => <<<HTML
        <h2>Bonjour {{username}} et bienvenue dans la communauté {{site}}</h2>
        Vos informations sont les suivantes :</p>
        <ul>
        <li>Nom d'utilisateur : {{username}}</li>
        <li>Adresse mail : {{email}}</li>
        <li>Numéro de membre : M{{id}}</li>
        <li>Date d'inscription : {{signup_date}}</li>
        </ul>
      HTML,
  'mail_info_body_text' => <<<TEXT
        Bonjour {{username}} et bienvenue dans la communauté {{site}}

        Vos informations sont les suivantes :
        - Nom d'utilisateur : {{username}}
        - Adresse mail : {{email}}
        - Numéro de membre : M{{id}}
        - Date d'inscription : {{signup_date}}
      TEXT,
];
