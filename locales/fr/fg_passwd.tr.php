<?php

$tr = [
  '_' => 'fg_passwd',
  '_todo_level' => 0,
  '_last_author' => 'Corentin',
  '_last_modif' => 1754926474,
  'title' => 'Mot de passe oublié',
  'mail_sent' => 'Consultez vos mails, un lien de réinitialisation vous y a été envoyé',
  'invalid_or_expired' => 'Ce token est invalide ou a expiré. Veuillez recommencer',
  'pwd_mismatch' => 'Les deux mots de passe saisis ne correspondent pas',
  'pwd_too_short' => 'Votre mot de passe doit contenir au moins 8 caractères',
  'pwd_no_reuse' => 'Le nouveau mot de passe doit être différent du dernier utilisé',
  'intro_text' => 'Remplissez le formulaire ci-dessous pour demander la réinitialisation de votre mot de passe {{site}}',
  'choose_new_pwd' => 'Remplissez le formulaire ci-dessous pour choisir votre nouveau mot de passe',
  'login_field' => 'Nom d\'utilisateur ou adresse mail',
  'request_btn' => 'Réinitialiser',
  'new_password' => 'Nouveau mot de passe&nbsp;:',
  'confirm_password' => 'Nouveau mot de passe (Vérification)&nbsp;:',
  'gen-psw' => 'Générer un mot de passe',
  'reset_btn' => 'Confirmer',
  'js-to-gen' => 'Activez JavaScript si vous souhaitez générer un mot de passe via le site',
  'mail_reset_subject' => 'Réinitialisation de mot de passe',
  'mail_reset_body_html' => <<<HTML
        <p>Bonjour {{username}},<br>
        Cliquez sur ce lien valable 1h pour choisir votre nouveau mot de passe&nbsp;:<br>
        <a href="{{link}}">Choisir mon mot de passe</a>.</p>
      HTML,
  'mail_reset_body_text' => <<<TEXT
          <p>Bonjour {{username}},
          Cliquez sur ce lien valable 1h pour choisir votre nouveau mot de passe:
        {{link}}
      TEXT,
];
