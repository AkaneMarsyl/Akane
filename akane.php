<?php

/*-----------------------------------------------------------------------------------
|                                                                                   |
|                                   Akane v1.0                                      |
|                                                                                   |
|    Description: Akane est un script d'imageboard (forum à image) écrit en PHP.    | 
|                 Il permet à des utilisateurs anonymes de partager des images      | 
|                 et du texte au travers de fils de discussion.                     |
|                                                                                   |
|         Auteur: Marsyl                                                            |
|                                                                                   |
|           Site: https://www.akane-ch.org/                                         |
|                                                                                   |
|                                                                                   |
-----------------------------------------------------------------------------------*/

/*-------Installation--------*/

    /*
    1) Créez un dossier à la racine du serveur. Mettez-y le fichier akane.php puis créez les dossiers res/ src/ et thumb/.

        SERVER_ROOT/
        |
        +-- <nom du forum>/
            |
            +-- Akane.php
            |
            +-- res/
            |
            +-- src/
            |
            +-- thumb/


    2) Créez une base de donnée ("imageboard" par défaut, sinon renommez dans les paramètres plus bas).

    3) Remplissez les paramètres ci-dessous.

    4) Lancez le fichier akane.php dans un navigateur.
    */

/*-------Paramètres--------*/

    //Logo
    define('LOGO','/img/logo.svg');

    //Environnement
    define('TITLE', '/e/ - Example'); //nom du forum
    define('ROOT','/e/'); //racine du forum
    define('RES_FOLDER', 'res/'); //dossier des pages
    define('IMG_FOLDER', 'src/'); //dossier des images
    define('THUMB_FOLDER', 'thumb/'); //dossier des miniatures
    define('ANON_NAME', 'Sine Nomine'); //nom par défaut
    define('MAX_BUMP', 50); //bump max par sujet
    define('MAX_REPLIES', 200); //réponses max par sujet
    define('THREADS_PER_PAGE', 5); //nombres de sujets par pages
    define('MAX_PAGES', 10); //nombre maximum de pages
    define('INDEX_PREVIEW', 5); //nombre de réponses à affichier par sujet dans l'index
    define('PASSWORD_SALT', '$2y$10$'.'**********************'); //sel de chiffrement. 22 caractères obligatoires pour blowfish

    //Messages
    define('COOLDOWN', 15); //temps à attendre entre deux messages
    define('MSG_MAX_LENGTH', 5000); //taille maximale d'un message
    define('MAX_FILESIZE',3*1024*1024); //taille du fichier en Mo
    define('FILE_TYPES', [ //formats de fichiers acceptés
        'jpg'   => 'image/jpeg',
        'png'   => 'image/png',
        'gif'   => 'image/gif',
        ]);
    define('IMG_THUMB', [ //taille de la miniature de l'OP
        'WIDTH'     => 248,
        'HEIGHT'    => 248,
        ]);
    define('IMG_THUMB_REPLY', [ //taille de la miniature des réponses
        'WIDTH'     => 124,
        'HEIGHT'    => 124,
    ]);

    //Base de données
    define('DB_USER','root');
    define('DB_PASSWORD', '');
    define('DB_NAME','imageboard');
    define('DB_HOST','localhost');
    define('DB_POST_TABLE','e_posts');
    define('DB_BANS_TABLE', 'bans');
    define('DB_ADMINS_TABLE', 'admins');

/*-------Initialisation-------*/

    //Paramétrage de PHP
    setlocale(LC_TIME,'fr_FR');
    date_default_timezone_set('Europe/Paris');
    error_reporting(E_ALL);
    session_start();
    setcookie(session_name(), session_id(), time() + 2592000);
    //Récupération des variables globales
    extract($_POST,EXTR_SKIP);
    extract($_GET,EXTR_SKIP);
    $upfile_name=isset($_FILES["upfile"]["name"]) ? $_FILES["upfile"]["name"] : "";
    $upfile=isset($_FILES["upfile"]["tmp_name"]) ? $_FILES["upfile"]["tmp_name"] : "";

    //Initialisation de la base de données
    if(!$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME, DB_USER, DB_PASSWORD, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING])){
        serverMessage('Échec de la connection à la base de données');
        die();
    }
    if(!@$pdo->query('SELECT 1 FROM '.DB_POST_TABLE.' LIMIT 1')){
        $pdo->query('CREATE TABLE IF NOT EXISTS '.DB_ADMINS_TABLE.' (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `password` varchar(255) NOT NULL,
                PRIMARY KEY (`id`)
                ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
            CREATE TABLE IF NOT EXISTS '.DB_BANS_TABLE.' (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `IP` varchar(255) NOT NULL,
                `reason` text DEFAULT NULL,
                `expires` int(11) NOT NULL DEFAULT "0",
                PRIMARY KEY (`id`)
            ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
            DROP TABLE IF EXISTS '.DB_POST_TABLE.' ;
            CREATE TABLE IF NOT EXISTS '.DB_POST_TABLE.' (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `bump` bigint(20) NOT NULL DEFAULT "0",
                `locked` BOOLEAN NOT NULL DEFAULT FALSE,
                `sticky` BOOLEAN NOT NULL DEFAULT FALSE,
                `parent` int(11) NOT NULL,
                `name` varchar(255) DEFAULT NULL,
                `tripcode` varchar(255) DEFAULT NULL,
                `IP` varchar(255) NOT NULL,
                `email` varchar(255) DEFAULT NULL,
                `subject` varchar(255) DEFAULT NULL,
                `message` text,
                `upfile_name` varchar(255) DEFAULT NULL,
                `md5` varchar(255) DEFAULT NULL,
                `file` varchar(255) DEFAULT NULL,
                `thumbnail` varchar(255) DEFAULT NULL,
                `replies` text NULL,
                `password` varchar(255) DEFAULT NULL,
                PRIMARY KEY (`id`)
                ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;');
        header('Refresh: 2; URL='.ROOT);
        update();
        serverMessage('Installation terminée');
    }

/*-------Fonctions HTML-------*/

    /*En-tête*/
    function head(&$data){
        $data.='<!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta http-equiv="content-type" content="text/html;charset=UTF-8">
            <meta http-equiv="cache-control" content="max-age=0">
            <meta http-equiv="cache-control" content="no-cache">
            <meta http-equiv="expires" content="0">
            <meta http-equiv="expires" content="Tue, 01 Jan 1980 1:00:00 GMT">
            <meta http-equiv="pragma" content="no-cache">
            <meta name="viewport" content="width=device-width,initial-scale=1">
            <meta name="keywords" content="imageboard, forum, culture, japonaise, japon, anime, manga, nsfw">
            <link rel="shortcut icon" href="/img/favicon.ico">
            <title>'.TITLE.'</title>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script>
                function reloadPage(){
                    location.reload(true);
                }
                function quotePost(postId){
                    $("#message").val($("#message").val() + postId + "\n").focus();
                    return false;
                }
                window.addEventListener(\'DOMContentLoaded\', (event) => {
                    if (window.location.hash){
                        if (window.location.hash.match(/^#q[0-9]+$/i) !== null) {
                            var postId = window.location.hash.match(/^#q[0-9]+$/i)[0].substr(2);
                            if (postId != \'\') {
                                quotePost(\'>>\' + postId);
                            }
                        }
                    }

                    $(\'.backlink, .replyLink\').mouseenter(function(){
                        var num = $(this).text().substr(2);
                        var ptop = $(this).offset().top - 20;
                        var pleft = $(this).offset().left;
                        $(\'body\').append(\'<div class="floatReply">\' + $(\'#\' + num).html() + \'</div>\');
                        $(\'.floatReply\').css({position: \'absolute\', top: (ptop - $(\'.floatReply\').height()), left: pleft + 40});
                    }).mouseleave(function(){
                        $(\'.floatReply\').remove();
                    });

                    $(\'form img\').mouseenter(function(){
                        var src = $(this).parent(\'a\').attr(\'href\');
                        $(\'html\').append(\'<div class="imgLarge" style="position:fixed;top:0;right:0;height:100vh;"><img style="top:0;right:0;max-height:100%;max-width:60vw;" src="\'+src+\'"></div>\');
                    }).mouseleave(function(){
                        $(\'.imgLarge\').remove();
                    });
                });
            </script>
            <style>
                html{
                    height:100%;
                }
                html, body{
                    background:linear-gradient(180deg, rgb(44, 156, 255) 0%, rgb(240, 240, 240) 200px);
                }
                body{
                    font-family:Arial;
                    font-size:12pt;
                    margin:0;
                    padding:8px;
                }
                footer{
                    font-size:8pt;
                }
                .logo{
                    margin:0;
                    text-align:center;
                }
                .logo img{
                    width: 100%;
                    max-width:750px;
                }
                .postform{
                    font-size:16px;
                    margin:0;
                    text-align:left;
                    width:100%;
                    padding-right:8px;
                }
                .postform ul{
                    font-size:8pt;
                    font-weight:normal;
                    padding:0;
                    margin:0;
                    margin-left:8px;
                }
                .formlabel{
                    background-color:#82a1c1;
                    color:white;
                    font-weight:bold;
                }
                .doc{
                    width:100%;
                    max-width:750px;
                    margin:auto;
                }
                .box{
                    background-color:#E6F0FD;
                    box-shadow:0px 0px 6px 0.5px #000000;
                }
                .boxtitle h5{
                    background:linear-gradient(180deg, rgb(58, 114, 255), rgb(81, 99, 128));
                    color:#FFFFFF;
                    font-size:16pt;
                    font-weight:bold;
                    margin-bottom: 0px;
                    margin-top:8px;
                    padding-left:8px;
                }
                .boxcontent{
                    padding:8px;
                }
                .boardtype{
                    font-weight:bold;
                }
                .lastsubjects{
                    width:100%;
                    border: 1px solid black;
                }
                hr{
                    clear:both;
                }
                a{
                    color:black;
                }
                a:hover{
                    color:red;
                }
                button{
                    color:white;
                    background-color:#378fea;
                    border-radius:4px;
                    border:none;
                    font-weight:bold;
                }
                button a{
                    color:white;
                    text-decoration:none;
                }
                button a:hover{
                    color:white;
                }
                button:hover{
                    cursor:pointer;
                    box-shadow:0px 0px 1px 1px #888
                }
                .paginate{
                    padding:2px;
                    border:1px solid black;
                }
                .paginate td{
                    padding:2px;
                    border:1px solid black;
                }
                .postStatus{
                    color:red;
                    font-weight:bold;
                }
                .postImg{
                    float:left
                }
                .reply, .floatReply{
                    background-color:#ffffff;
                    box-shadow: 0px 0px 4px #040404;
                    border-radius: 12px;
                    padding:0;
                    padding-bottom:8px;
                }
                .floatReply img{
                    margin-right:12px;
                }
                .reply:target{
                    background-color:#c4e3ff;
                }
                .replyLink{
                    color:red;
                    text-decoration:underline;
                }
                .replyhead{
                    font-size:10pt;
                    margin: 0;
                    border-radius: 12px 12px 0px 0px;
                    padding: 8px;
                    background: linear-gradient(rgb(179, 212, 255),rgb(115, 164, 217));
                }
                .replybody{
                    padding: 8px;
                }
                .backlink{
                    font-size:10pt;
                    text-decoration:underline;
                }
                .quote{
                    color:#789922;
                }
                form a{
                    text-decoration:none;
                }
                form img{
                    margin-right:12px;
                }
                .navlinks{
                    clear:both;
                    background:linear-gradient(rgb(143, 181, 240),rgb(125, 176, 221),rgb(77, 162, 213));
                    padding:8px;
                    margin-top:8px;
                    margin-bottom:8px;
                    box-shadow: 0px 0px 4px 1px #3b708a;
                }
                .navlinks button{
                    background: linear-gradient(rgb(80, 148, 255),rgb(55, 88, 109));
                    font-size: 14pt;
                    font-weight: bold;
                    margin-right: 8px;
                }
                .OPImg{
                    margin-bottom:8px;
                    float:left;
                }
                .catalogrow{
                    cursor:pointer;
                }
                .catalogrow:hover{
                    background-color:#c2c2c2;
                }
                .cataloglabel{
                    background:linear-gradient(180deg, rgb(124, 180, 240), rgb(104, 134, 183));
                    color:white;
                }
                .subject{
                    color:#cc1105;
                    font-size:16px;
                    font-weight:bold;
                }
                .name{
                    color: #0052aa;
                    font-weight: bold;
                }
                .tripcode{
                    color:#228854;
                }
            </style>
        </head>
        <body><a id="up"></a>';
    }

    /*Barre de navigation et titre*/
    function title(&$data){

        global $logged;
        
        $data .= '<div>
            '.($logged ? ' [<span style="color:red;font-weight:bold;">Connecté: '.$_SESSION['auth']['name'].'</span>] [<a href="akane.php?admin&logout">Se déconnecter</a>] [<a href="akane.php?admin&banlist">Liste des bannis</a>] [<a href="akane.php?admin&rebuildAll">Tout reconstruire</a>]' : ''/*'[<a href="'.ROOT.'akane.php?admin">Administration</a>]'*/).'
        </div>
        <div class="logo">
            <img src="'.LOGO.'">
        </div>
        <div>
            <hr style="max-width:750px">
            <h1 align="center">'.TITLE.'</h1>
            <hr style="max-width:750px">
        </div>';
    }

    /*Formulaire*/
    function form(&$data, $id = null){

        $data.='
        <div class="box" style="max-width:750px;'.(!$id ? 'margin:auto;' : '').'">
            <div class="boxtitle">
                <h5>'.(!$id ? 'Créer un nouveau sujet' : 'Répondre au sujet No.'.$id).'</h5>
            </div>
            <form action="'.ROOT.'akane.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="parent" value="'.(!$id ? '0' : $id).'">
                <table class="postform">
                    <tr>
                        <td><input type="text" placeholder="Nom (facultatif)" name="name" style="width:100%;"></td>
                    </tr>
                    <tr>
                        <td><input type="text" placeholder="E-mail (facultatif)" name="email" style="width:100%;"></td>
                    </tr>';
                    if($id == 0){
                    $data .= '<tr>
                        <td><input type="text" placeholder="Sujet (facultatif)" name="subject" style="width:100%;"></td>
                    </tr>';
                    }
                    $data .= '<tr>
                        <td><textarea id="message" placeholder="Message" name="message" max="8000" style="width:100%;height:80px;"></textarea></td>
                    </tr>
                    <tr>
                        <td><input type="file" name="upfile"></td>
                    </tr>
                    <tr>
                        <td><input type="password" placeholder="Mot de passe (pour supprimer)" name="password" style="width:100%;"></td>
                    </tr>
                    <tr>
                        <td><input type="submit" name="submit" value="Envoyer"></td>
                    </tr>
                    <tr>
                        <th colspan="2">
                            <ul>
                                <li>Il faut au moins une image ou du texte pour répondre.</li>
                                <li>Les formats supportés sont JPG, PNG et GIF.</li>
                                <li>Taille maximale du fichier: 3Mo.</li>
                            </ul>
                        </th>
                    </tr>
                </table>
            </form>
        </div>';
    }

    /*Liens de navigation*/
    function navlinks(&$data, $id = null, $position = false){
        
        global $logged;

        $data .= '
        <div class="navlinks">
        <span>
        <button><a href="/">Accueil</a></button>'.($id ? '<button><a href="'.($logged ? 'akane.php?admin&page=0' : ROOT).'">Index</a></button>' : '').'<button><a href="'.ROOT.'catalogue">Catalogue</a></button>'.(!$position  ? '<button><a href="#down">&#9660;</a></button>' : '<button><a href="#up">&#9650;</a></button>').'<button type="button" onclick="reloadPage()">Rafraîchir</button>
        </span>
        '.(!$position ? '
        </div>
        <form action="'.ROOT.'akane.php?delete" method="get">' : '
        <span style="float:right;">
            <input type="password" placeholder="Mot de passe" name="password" size="8"><input type="submit" name="deletePost" value="Supprimer">
        </span>
        </div>
        </form>');
    }

    /*OP*/
    function OP(&$data, $id, $index = false){
        
        global $pdo, $logged;

        $stmt = $pdo->prepare('SELECT * FROM '.DB_POST_TABLE.' WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $OP = $stmt->fetch();

        $data .= '
        <div id="'.$OP['id'].'">
        Fichier:<a href="'.ROOT.IMG_FOLDER.$OP['file'].'" target="_blank">
            '.(strlen($OP['upfile_name']) > 20 ? substr($OP['upfile_name'], 0, 20).'...'.substr($OP['upfile_name'], -4) : $OP['upfile_name']).'
        </a>
        [<a target="_blank" href="https://saucenao.com/search.php?url=https://www.akane-ch.org'.ROOT.THUMB_FOLDER.$OP['thumbnail'].'">SauceNao</a>]
        <br>
        <a href="'.ROOT.IMG_FOLDER.$OP['file'].'" target="_blank">
            <img class="OPImg" src="'.ROOT.THUMB_FOLDER.$OP['thumbnail'].'" title="'.$OP['upfile_name'].'" alt="'.$OP['upfile_name'].'">
        </a>
        <input type="checkbox" name="del" value="'.$OP['id'].'">
        '.(!empty($OP['subject']) ? '<span class="subject">'.$OP['subject'].'</span>' : '').'
        <span class="name">
            '.(!empty($OP['email']) ? '<a href="mailto:'.$OP['email'].'">'.$OP['name'].'</a>' : $OP['name']).'
        </span>
        '.(!empty($OP['tripcode']) ? '<span class="tripcode">'.$OP['tripcode'].'</span>' : '').'
        <span>
            '.date('d/m/y \à H:i:s', strtotime($OP['date'])).'
        </span>
        <span>
            <a href="'.ROOT.RES_FOLDER.$OP['id'].'#'.$OP['id'].'">No.</a><a href="'.ROOT.RES_FOLDER.$OP['id'].'#q'.$OP['id'].'"'.(!$index ? ' onclick="javascript:quotePost(\'>>'.$OP['id'].'\')"' : null).'>'.$OP['id'].'</a>'.
            ($OP['locked'] ? ' [<span class="postStatus">Verrouillé</span>]' : '').
            ($OP['sticky'] ? ' [<span class="postStatus">Épinglé</span>]' : '').
            ($index ? '&nbsp;<button><a href="'.($logged ? 'akane.php?admin&res='.$OP['id'] : ROOT.RES_FOLDER.$OP['id']).'">Répondre</a></button>' : '').
            ($logged ? ' ['.$OP['IP'].'] 
            [<a href="akane.php?admin&del='.$OP['id'].'">Supprimer</a>] 
            [<a href="akane.php?admin&ban='.$OP['id'].'">Bannir</a>] '.
            ($OP['locked'] ? '[<a href="akane.php?admin&unlock='.$OP['id'].'">Déverrouiller</a>]' : '
            [<a href="akane.php?admin&lock='.$OP['id'].'">Verrouiller</a>]').
            (!$OP['sticky'] ? '[<a href="akane.php?admin&stick='.$OP['id'].'">Épingler</a>]' : '
            [<a href="akane.php?admin&unstick='.$OP['id'].'">Désépingler</a>]') : '').' '.
            $OP['replies'].'
        </span>
        <br>
        <p>
            '.$OP['message'].'
        </p>
        <br>
        </div>';
    }

    /*Réponses*/
    function replies(&$data, $parent, $index = false){

        global $pdo, $logged;

        $stmt = $pdo->prepare('SELECT * FROM '.DB_POST_TABLE.' WHERE parent = :parent ORDER BY id DESC');
        $stmt->execute(['parent' => $parent]);
        $replies = $stmt->fetchAll();
        if($index){
            $hidden = count($replies) - INDEX_PREVIEW;
        }else{
            $hidden = 0;
        }
        $counter = 0;
        foreach(array_reverse($replies) as $reply){
            $data .= '<table style="margin-top:6px;'.($counter < $hidden ? 'display:none;' : '').'">
                <tr>
                    <td style="float:left;color:#9b9b9b;">
                        '.sprintf('%03d', ($counter + 1)).'
                    </td>
                    <td id="'.$reply['id'].'" class="reply">
                        <div class="replyhead">
                            <input type="checkbox" name="del" value="'.$reply['id'].'">
                            <span class="name">
                                '.(!empty($reply['email']) ? '<a href="mailto:'.$reply['email'].'">'.$reply['name'].'</a>' : $reply['name']).'
                            </span>
                            '.(!empty($reply['tripcode']) ? '<span class="tripcode">'.$reply['tripcode'].'</span>' : '').'
                            <span>
                                '.date('d/m/y \à H:i:s', strtotime($reply['date'])).'
                            </span>
                            <span>
                                <a href="'.ROOT.RES_FOLDER.$reply['parent'].'#'.$reply['id'].'">No.</a><a href="'.ROOT.RES_FOLDER.$reply['parent'].'#q'.$reply['id'].'" '.(!$index ? 'onclick="javascript:quotePost(\'>>'.$reply['id'].'\')"' : '').'>'.$reply['id'].'</a>'.($logged ? ' ['.$reply['IP'].'] [<a href="akane.php?admin&del='.$reply['id'].'">Supprimer</a>]  [<a href="akane.php?admin&ban='.$reply['id'].'">Bannir</a>]' : '').' '.$reply['replies'].'
                            </span>
                            <br>';
                            if(!empty($reply['file'])){
                                $data .= 'Fichier:<a href="'.ROOT.IMG_FOLDER.$reply['file'].'" target="_blank">
                                '.(strlen($reply['upfile_name']) > 20 ? substr($reply['upfile_name'], 0, 20).'...'.substr($reply['upfile_name'], -4) : $reply['upfile_name']).'
                            </a>
                            [<a target="_blank" href="https://saucenao.com/search.php?url=https://www.akane-ch.org'.ROOT.THUMB_FOLDER.$reply['thumbnail'].'">SauceNao</a>]
                        </div>
                        <div class="replybody">
                            <a href="'.ROOT.IMG_FOLDER.$reply['file'].'" target="_blank">
                                <img class="postImg" src="'.ROOT.THUMB_FOLDER.$reply['thumbnail'].'" title="'.$reply['upfile_name'].'" alt="'.$reply['upfile_name'].'">
                            </a>';
                            }else{
                                $data .= '</div>
                                <div class="replybody">';
                            }
                            $data .= '<p>
                                '.$reply['message'].'
                            </p>
                        </div>
                    </td>
                </tr>
            </table>';
            $counter++;
        }
    }

    /*Catalogue*/
    function catalog(){

        global $pdo;

        $data = '';
        head($data);
        title($data);
        $data .= '
        <div class="navlinks">
            <button><a href="/">Accueil</a></button><button><a href="'.ROOT.'">Index</a></button><button onclick="reloadPage()">Rafraîchir</button>
        </div>
        <div class="box">
        <div class="boxtitle">
            <h5>Catalogue</h5>
        </div>
        <div class="boxcontent">
            <table class="lastsubjects">
                <tr>
                    <td class="cataloglabel">No.</td>
                    <td class="cataloglabel">Sujet/Message</td>
                    <td class="cataloglabel">Auteur</td>
                    <td class="cataloglabel">Date</td>
                    <td class="cataloglabel">Réponses</td>
                </tr>';
            $posts = $pdo->query('SELECT * FROM '.DB_POST_TABLE.' WHERE parent = 0 ORDER BY sticky DESC, bump DESC')->fetchAll();
            foreach($posts as $post){
                $data .= '
                <tr class="catalogrow" onclick="window.open(\''.ROOT.RES_FOLDER.$post['id'].'\')">
                    <td>'.$post['id'].'</td>
                    <td>'.($post['subject'] ? '<strong>'.substr($post['subject'], 0, 80).'</strong><br>' : '').substr(strip_tags($post['message']), 0, 80).'</td>
                    <td>'.$post['name'].'</td>
                    <td>'.date('d/m/y \à H:i:s', strtotime($post['date'])).'</td>
                    <td>'.countReplies($post['id']).'</td>
                </tr>';
            }
            $data .= '</table>
                </div>';
            $output = fopen('catalogue.html', 'w') or die(serverMessage('Impossible de trouver le fichier'));
            fwrite($output, $data);
            fclose($output);
    }

    /*Accès administration*/
    function adminView(){

        global $pdo, $logout, $banlist, $del, $rebuildAll, $logged, $page, $res, $ban, $banIP, $publicban, $liftban, $postID, $submitban, $reason, $length, $stick, $unstick, $lock, $unlock;
    
        if($logged){
            if(isset($del)){
                deletePost($del);
                header('Refresh: 2; URL='.ROOT.'akane.php?admin');
                serverMessage('Message No.'.$del.' supprimé.');
                exit();
            }elseif(isset($rebuildAll)){
                rebuildAll();
                header('Refresh: 2; URL='.ROOT.'akane.php?admin');
                serverMessage('Forum reconstruit.');
                exit();
            }elseif(isset($page)){
                $data = '';
                update($page, true);
            }elseif(isset($res)){
                $data = '';
                updateThread($res, true);
            }elseif(isset($submitban)){
                banIP($banIP, $reason, $length, (isset($publicban) ? $publicban : false), $postID);
            }elseif(isset($liftban)){
                liftBan($postID);
                header('Location: '.ROOT.'akane.php?admin&banlist');
                exit();
            }elseif(isset($lock)){
                lockThread($lock);
                header('Location: '.ROOT.'akane.php?admin&res='.$lock);
                exit();
            }elseif(isset($unlock)){
                unlockThread($unlock);
                header('Location: '.ROOT.'akane.php?admin&res='.$unlock);
                exit();
            }elseif(isset($stick)){
                stickThread($stick);
                header('Location: '.ROOT.'akane.php?admin&res='.$stick);
                exit();
            }elseif(isset($unstick)){
                unstickThread($unstick);
                header('Location: '.ROOT.'akane.php?admin&res='.$unstick);
                exit();
            }elseif(isset($ban)){

                $post = $pdo->query('SELECT * FROM '.DB_POST_TABLE.' WHERE id = '.$ban)->fetch();
                $data = '';
                head($data);
                title($data);
                $data .= '<h3>Régistration pour bannissement</h3><br>';
                $data .= '
                <form action="akane.php" method="get">
                    <input type="hidden" name="admin">
                    <input type="hidden" name="banIP" value="'.$post['IP'].'">
                    <input type="hidden" name="postID" value="'.$post['id'].'">
                    <table class="postform" style="width:100%;">
                        <tr>
                            <td class="formlabel"><label>IP</label></td>
                            <td class="formlabel"><label>Post No.</label></td>';
                        if(!empty($post['thumbnail'])){
                            $data .= '<td class="formlabel"><label>Image</label></td>';
                        }
                        $data .= '<td class="formlabel"><label>Message</label></td>
                            <td class="formlabel"><label>Raison</label></td>
                            <td class="formlabel"><label>Durée</label></td>
                            <td class="formlabel"><label>Public ?</label></td>
                            <td class="formlabel"><label>Action</label></td>
                        </tr>
                        <tr>
                            <td style="border:1px solid black;">'.$post['IP'].'</td>
                            <td style="border:1px solid black;">'.$post['id'].'</td>';
                        if(!empty($post['thumbnail'])){
                            $data .= '<td style="border:1px solid black;"><img src="'.ROOT.THUMB_FOLDER.$post['thumbnail'].'" height="124"></td>';
                        }
                        $data .= '<td style="border:1px solid black;">'.$post['message'].'</td>
                        <td style="border:1px solid black;">
                            <select name="reason">
                                <option value="Contenu illégal.">Contenu illégal</option>
                                <option value="Message troll.">Troll</option>
                                <option value="Shitpost.">Shitpost</option>
                                <option value="Spam">Spam</option>
                                <option value="Contenu NSFW en dehors des forums NSFW">NSFW</option>
                                <option value="Partage d\'infos personnelles">Infos persos</option>
                                <option value="Pas de pub à but lucratif.">Publicité lucrative</option>
                            </select>
                        </td>
                        <td style="border:1px solid black;">
                            <select name="length">
                                <option value="3600">Une heure</option>
                                <option value="21600">Six heures</option>
                                <option value="86400">Un jour</option>
                                <option value="172800">Deux jours</option>
                                <option value="259200">Trois jours</option>
                                <option value="604800">Une semaine</option>
                                <option value="2592000">Un mois</option>
                                <option value="0">Permanant</option>
                            </select>
                        </td>
                        <td style="border:1px solid black;"><input type="checkbox" name="publicban"></td>
                        <td style="border:1px solid black;"><input type="submit" name="submitban" value="Bannir"></td>
                        </tr>

                    </table>
                </form>';
                echo($data);
            }elseif(isset($banlist)){
                $posts = $pdo->query('SELECT * FROM '.DB_BANS_TABLE.' ORDER BY id DESC')->fetchAll();
                $data = '';
                head($data);
                title($data);
                $data .= '<h3 align="center">Bannissements actifs</h3><br>';
                foreach($posts as $post){
                    $data .= '<form action="akane.php" method="get" style="max-width:750px;margin:auto;">
                        <input type="hidden" name="admin">
                        <input type="hidden" name="ban">
                        <input type="hidden" name="banIP" value="'.$post['IP'].'">
                        <input type="hidden" name="postID" value="'.$post['id'].'">
                        <table class="postform" style="width:100%;">
                            <tr>
                                <td class="formlabel"><label>Ban No.</label></td>
                                <td class="formlabel"><label>IP</label></td>
                                <td class="formlabel"><label>Raison</label></td>
                                <td class="formlabel"><label>Date d\'expiration</label></td>
                                <td class="formlabel"><label>Action</label></td>
                            </tr>
                            <tr>
                                <td style="border:1px solid black;">'.$post['id'].'</td>
                                <td style="border:1px solid black;">'.$post['IP'].'</td>
                                <td style="border:1px solid black;">'.$post['reason'].'</td>
                                <td style="border:1px solid black;">'.date('d/m/y à H:i:s', $post['expires']).'</td>
                                <td style="border:1px solid black;"><input type="submit" name="liftban" value="Lever"></td>
                            </tr>
                        </table>
                    </form>';
                    }
                    echo($data);
            }elseif(isset($logout)){
                session_unset();
                session_destroy();
                header('Location: '.ROOT);
                exit();
            }else{
                $data = '';
                update(0, true);
            }
        }else{
            $data = '';
            head($data);
            $data .= '<div align="center">
            <h2>Administration</h2>
            <form action="akane.php?admin" method="post">
            <label>Mot de passe</label>
            <input type="hidden" name="admin">
            <input type="password" name="password">
            <input type="submit" name="login" value="Connexion">
            </form>
            </div>
            </body>
            </html>';
            echo($data);
        }
    }

    /*Pagination*/
    function paginate(&$data, $page, $threadCount){

        global $logged;

        $pageTotal = ceil($threadCount / THREADS_PER_PAGE);

        if($logged){
            $data .= '
            <div>
                <table class="paginate">
                <tr>
                '.($page > 0 ? '<td><a href="akane.php?admin&page='.($page - 1).'">Précédent</a></td>' : '');
                for($j = 0; $j < $pageTotal; $j++){
                    if($j == $page){
                        $data .= '<td style="font-weight:bold">'.($j + 1).'</td>';
                    }else{
                        $data .= '<td style="text-decoration:underline;"><a href="akane.php?admin&page='.$j.'">'.($j + 1).'</a></td>';
                    }
                }
            $data .= ($page + 1 < $pageTotal ? '<td><a href="akane.php?admin&page='.($page + 1).'">Suivant</a></td>' : '').'
            </tr>
            </table>
            </div>';
        }else{
        $data .= '
        <div>
            <table class="paginate">
            <tr>
            '.($page > 0 ? '<td><a href="'.($page == 1 ? ROOT : ROOT.$page).'">Précédent</a></td>' : '');
            for($j = 0; $j < $pageTotal; $j++){
                if($j == $page){
                    $data .= '<td style="font-weight:bold">'.($j + 1).'</td>';
                }else{
                    $data .= '<td style="text-decoration:underline;"><a href="'.($j == 0 ? ROOT : ROOT.($j + 1)).'">'.($j + 1).'</a></td>';
                }
            }
        $data .= ($page + 1 < $pageTotal ? '<td><a href="'.ROOT.($page + 2).'">Suivant</a></td>' : '').'
        </tr>
        </table>
        </div>';
        }
    }

    /*Pied de page*/
    function footer(&$data){

        $data.='
        <footer id="down">
            <p style="text-align:center">
                - Les utilisateurs seuls sont responsables des messages et images qu\'ils envoient. Akane-ch.org, ainsi que son administration, se dégagent de toute opinion ou intention qui pourrait y être exprimée. -
            </p>
        </footer>
        </body>
        </html>';
    }

    /*Message de succès*/
    function serverMessage($str){
        $data='';
        head($data);
        $data .= '<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
                <h1>'.$str.'</h1>
            </div>
            </body>
            </html>';
        echo($data);
    }

/*-------Fonctions PHP-------*/

    /*Mise à jour de l'index*/
    function update($page = 0){

        global $pdo, $logged;

        $threadCount = countThreads();

        if($threadCount > MAX_PAGES * THREADS_PER_PAGE){
            prune();
            $threadCount--;
        }

        if($logged){
            $maxPages = $page + 1;
        }else{
            $maxPages = MAX_PAGES;
        }

        for($i = $page; $i < $maxPages; $i++){
            $data='';
            head($data);
            title($data);
            form($data);
            navlinks($data, null);
            $stmt = $pdo->query('SELECT id FROM '.DB_POST_TABLE.' WHERE parent = 0 ORDER BY sticky DESC, bump DESC LIMIT '.THREADS_PER_PAGE.' OFFSET '.($i * THREADS_PER_PAGE));
            $OPs = $stmt->fetchAll();
            if(!$OPs && $i>0){break;}
            foreach($OPs as $OP){
                OP($data, $OP['id'], true);
                $omitted = countReplies($OP['id']) - 5;
                $omitted <= 0 ? '' : $data .= '<span style="color:grey;">'.$omitted.' réponses omises, cliquez sur \'Répondre\' pour tout voir.</span>';
                replies($data, $OP['id'], true);
                if($OP !== end($OPs)){
                    $data .= '<hr>';
                }
            }
            navlinks($data, null, true);
            paginate($data, $i, $threadCount);
            footer($data);
            if(!$logged){
                $output = fopen(($i > 0 ? 1+$i.'.html' : 'index.html'), 'w') or die(serverMessage('Impossible de trouver le fichier'));
                fwrite($output, $data);
                fclose($output);
            }
            else{
                echo($data);
                exit();
            }
        }
    }

    /*Mise à jour d'un seul sujet*/
    function updateThread($id){

        global $logged;

        $data='';
        head($data);
        title($data);
        navlinks($data, $id);
        OP($data, $id);
        replies($data, $id);
        navlinks($data, $id, true);
        form($data, $id);
        $data .= '<hr>';
        footer($data);
        if(!$logged){
            $output = fopen('..'.ROOT.RES_FOLDER.$id.'.html', 'w') or die(serverMessage('impossible de trouver le fichier'));
            fwrite($output, $data);
            fclose($output);
        }else{
            echo($data);
        }
    }

    /*Reconstruit tout le forum*/
    function rebuildAll(){

        global $pdo, $logged;

        $threads = $pdo->query('SELECT id FROM '.DB_POST_TABLE.' WHERE parent = 0')->fetchAll();

        $logged = false;
        foreach($threads as $thread){
            updateThread($thread['id']);
        }
        update();
        catalog();
        $logged = true;
    }

    /*Validation du fichier transferé*/
    function validateFile($upfile,$upfile_name){

        if($_FILES['upfile']['size'] > MAX_FILESIZE){
            serverMessage('Fichier trop grand');
            die();
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        if (false === $ext = array_search($finfo->file($_FILES['upfile']['tmp_name']), FILE_TYPES, true)) {
            serverMessage('Format incorrect');
            die();
        }
        $md5 = md5_file($upfile);
        $timestamp = time().substr(microtime(), 2, 3);
        if (!move_uploaded_file($_FILES['upfile']['tmp_name'], IMG_FOLDER.$timestamp.'.'.$ext)) {
            serverMessage('Erreur lors du transfert');
            die();
        }
        return [
            'upfile_name' => $upfile_name,
            'md5' => $md5,
            'timestamp' => $timestamp,
            'ext' => $ext,
        ];
    }

    /*Vérifie s'il y a doublon*/
    function fileExist($upfile_name){
        
        global $pdo;

        $checksum = md5_file($upfile_name);
        $sql = sprintf('SELECT md5 FROM %s WHERE md5 = \'%s\' LIMIT 1', DB_POST_TABLE, $checksum);
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $req = $stmt->fetch();
        if($req){
            serverMessage('Fichier en double');
            die();
        }

    }

    /*Création de la miniature*/
    function createThumbnail($fileInfo, $parent){
        extract($fileInfo);
        $fname = IMG_FOLDER.$timestamp.'.'.$ext;

        if($parent > 0){
            $width = IMG_THUMB_REPLY['WIDTH'];
            $height = IMG_THUMB_REPLY['HEIGHT'];
        }else{
            $width = IMG_THUMB['WIDTH'];
            $height = IMG_THUMB['HEIGHT'];
        }

        $size = GetImageSize($fname);
        switch ($size[2]) {
            case 1 :
                $im_in = @ImageCreateFromGIF($fname);
                if($im_in){break;}
                $im_in = @ImageCreateFromPNG($fname);
                if(!$im_in)return;
                break;
            case 2 : 
                $im_in = @ImageCreateFromJPEG($fname);
                if(!$im_in){return;}
                break;
            case 3 :
                $im_in = @ImageCreateFromPNG($fname);
                break;
            default : return;
        }

        if($size[0] > $width || $size[1] > $height){

            $key_w = $width / $size[0];
            $key_h = $height / $size[1];
            ($key_w < $key_h) ? $keys = $key_w : $keys = $key_h;
            $out_w = ceil($size[0] * $keys);
            $out_h = ceil($size[1] * $keys);
        }else{
            $out_w = $size[0];
            $out_h = $size[1];
        }

            $im_out = imagecreatetruecolor($out_w, $out_h);

            imagecopyresized($im_out, $im_in, 0, 0, 0, 0, $out_w, $out_h, $size[0], $size[1]);
            ImageJPEG($im_out, $path = THUMB_FOLDER.$timestamp.'s.jpg',60);

            imagedestroy($im_in);
            imagedestroy($im_out);

            return $path;
    }

    /*traitement du formulaire et envoi à la Base de données*/
    function processPost($parent,&$name,&$email,&$subject,&$message,&$uploadedFileInfo,&$password){
        
        global $pdo;

        if($parent == 0 && !$uploadedFileInfo){
            die(serverMessage('Il faut une image pour démarrer un sujet.'));
        }
        if($parent > 0 && !$message){
            if(!$uploadedFileInfo){
                die(serverMessage('Il faut au moins une image ou un message pour répondre'));
            }
        }
        if(strlen($name > 30)){die(serverMessage('Votre nom est trop long.'));}
        $name = htmlspecialchars($name);
        if(preg_match('/^(.*)##(.*)$/', $name, $match)){
            if(checkPassword(crypt($match[2], PASSWORD_SALT))){
                $name = $match[1].'<span style="color:red;font-weight:bold;"> ## Administrateur</span>';
            }else{
                die(serverMessage('Échec de l\'authentification.'));
            }
            $tripcode = null;
        }elseif(preg_match('/^(.*)#(.*)$/', $name, $match)){
            $name = $match[1];
            $tripcode = '!'.substr(crypt($match[2], PASSWORD_SALT), -10);
        }else{
            $tripcode = null;
        }
        if($name == ''){$name = ANON_NAME;}
        $IP = $_SERVER['REMOTE_ADDR'];
        $email = htmlspecialchars($email);
        if($email == 'sage'){$email = '';}
        $subject = htmlspecialchars($subject);
        $message = htmlspecialchars($message);
        $msglen = strlen($message);
        if($msglen > MSG_MAX_LENGTH){die(serverMessage('Message trop long, '.$msglen.'/'.MSG_MAX_LENGTH.' caractères.'));}
        $message = preg_replace('/\r/', '<br>', $message);
        preg_match_all('/(&gt;&gt;)([0-9]+)/', $message, $quotes);
        $message = formatMessage($message, $parent);
        if($uploadedFileInfo){
            extract($uploadedFileInfo);
            $upfile_name = htmlspecialchars($upfile_name);
            $file = $timestamp.'.'.$ext;
            $thumbnail = $timestamp.'s.jpg';
        }else{
            $upfile_name = null;
            $md5 = null;
            $file = null;
            $thumbnail = null;
        }
        if(!empty($password)){
            $password = password_hash($password, PASSWORD_BCRYPT);
        }else{
            $password = null;
        }

        $sql = 'INSERT INTO '.DB_POST_TABLE.' (parent, name, tripcode, IP, email, subject, message, upfile_name, md5, file, thumbnail, password) VALUES (:parent, :name, :tripcode, :IP, :email, :subject, :message, :upfile_name, :md5, :file, :thumbnail, :password)';
        $stmt= $pdo->prepare($sql);
        $stmt->execute(compact('parent', 'name', 'tripcode', 'IP', 'email', 'subject', 'message', 'upfile_name', 'md5', 'file', 'thumbnail', 'password'));

        $id = $pdo->lastInsertId();

        if($quotes[2]){
            bindQuotes($quotes[2], $parent, $id);
        }

        return $id;
    }

    /*Formate le message*/
    function formatMessage($message, $parent){

        $message = preg_replace("~[[:alpha:]]+://[^<>[:space:]]+[[:alnum:]/]~", "<a href=\"\\0\" target=\"_blank\" style=\"text-decoration:underline;\">\\0</a>", $message);
        $message = preg_replace('/(&gt;&gt;)([0-9]+)/', '<a class="replyLink" href="'.ROOT.RES_FOLDER.$parent.'#${2}">>>${2}</a>', $message);
        $message = preg_replace('/^(&gt;[^\>](.*))/m', '<span class="quote">${1}</span>', $message);

        return $message;
    }

    /*Envoi des réponses*/
    function bindQuotes(array $quotes, $parent, $id){

        global $pdo;

        $quotes = array_unique($quotes);
        foreach($quotes as $quote){
            $savedQuotes = $pdo->query('SELECT replies, id, parent FROM '.DB_POST_TABLE.' WHERE id = '.$quote)->fetch();
            if($savedQuotes['parent'] == $parent || $savedQuotes['id'] == $parent){
                $savedQuotes['replies'] .= '<span class="backlink"><a href="'.($parent > 0 ? ROOT.RES_FOLDER.$parent.'#'.$id : ROOT.RES_FOLDER.$id.'#'.$id).'">>>'.$id.'</a></span> ';
                $stmt = $pdo->prepare('UPDATE '.DB_POST_TABLE.' SET replies = :savedQuotes WHERE id ='.$quote);
                $stmt->execute(['savedQuotes' => $savedQuotes['replies']]);
            }
        }
    }

    /*Verrouille un sujet*/
    function lockThread($id){

        global $pdo, $logged;

        $pdo->query('UPDATE '.DB_POST_TABLE.' SET locked = 1 WHERE id = '.$id);
        $logged = false;
        updateThread($id);
        update();
        $logged = true;
    }

    /*Déverrouille un sujet*/
    function unlockThread($id){

        global $pdo, $logged;

        $pdo->query('UPDATE '.DB_POST_TABLE.' SET locked = 0 WHERE id = '.$id);
        $logged = false;
        updateThread($id);
        update();
        $logged = true;
    }

    /*Épingle un sujet*/
    function stickThread($id){

        global $pdo, $logged;

        $pdo->query('UPDATE '.DB_POST_TABLE.' SET sticky = 1 WHERE id = '.$id);
        $logged = false;
        updateThread($id);
        update();
        $logged = true;
    }

    /*Désépingle un sujet*/
    function unstickThread($id){

        global $pdo, $logged;

        $pdo->query('UPDATE '.DB_POST_TABLE.' SET sticky = 0 WHERE id = '.$id);
        $logged = false;
        updateThread($id);
        update();
        $logged = true;
    }

    /*Suppression de messages*/
    function deletePost($id, $password = null, $prune = false){
        
        global $pdo, $logged;

        $base_dir = realpath($_SERVER["DOCUMENT_ROOT"]);
        $delpwd = $pdo->query('SELECT password FROM '.DB_POST_TABLE.' WHERE id = '.$id)->fetchColumn();

        if($prune || $logged || password_verify($password, $delpwd)){
            $parent = $pdo->query('SELECT parent FROM '.DB_POST_TABLE.' WHERE id = '.$id)->fetchColumn();
            if($parent == 0){
                $files = $pdo->query('SELECT file FROM '.DB_POST_TABLE.' WHERE id = '.$id.' OR parent = '.$id)->fetchAll();
                $thumbnails = $pdo->query('SELECT thumbnail FROM '.DB_POST_TABLE.' WHERE id = '.$id.' OR parent = '.$id)->fetchAll();
                $pdo->query('DELETE FROM '.DB_POST_TABLE.' WHERE id = '.$id.';
                DELETE FROM '.DB_POST_TABLE.' WHERE parent = '.$id);
                @unlink($base_dir.ROOT.RES_FOLDER.$id.'.html');
                foreach($files as $file){
                    @unlink($base_dir.ROOT.IMG_FOLDER.$file['file']);
                }
                foreach($thumbnails as $thumbnail){
                    @unlink($base_dir.ROOT.THUMB_FOLDER.$thumbnail['thumbnail']);
                }
                if(!$prune){
                    $logged = false;
                    update();
                    $logged = true;
                }
            }else{
                $file = $pdo->query('SELECT file FROM '.DB_POST_TABLE.' WHERE id = '.$id)->fetchColumn();
                $thumbnail = $pdo->query('SELECT thumbnail FROM '.DB_POST_TABLE.' WHERE id = '.$id)->fetchColumn();
                $pdo->query('DELETE FROM '.DB_POST_TABLE.' WHERE id = '.$id);
                @unlink($base_dir.ROOT.IMG_FOLDER.$file);
                @unlink($base_dir.ROOT.THUMB_FOLDER.$thumbnail);
                $logged = false;
                updateThread($parent);
                update();
                $logged = true;
            }
        }else{
            header('Refresh: 2; URL='.ROOT);
            die(serverMessage('Erreur. Impossible de supprimer le message.'));
        }
    }

    /*Banni une IP*/
    function banIP($IP, $reason, $length, $publicBan, $id){

        global $pdo, $logged;

        if($length > 0){
            $expires = time() + $length;
        }else{
            $expires = 0;
        }
        $pdo->query('INSERT INTO '.DB_BANS_TABLE.' (IP, reason, expires) VALUES (\''.$IP.'\', \''.$reason.'\', \''.$expires.'\')');

        if($publicBan){
            $post = $pdo->query('SELECT message, parent, id FROM '.DB_POST_TABLE.' WHERE id = '.$id)->fetch();
            $post['message'] .= '<br><span style="font-size:24pt;font-weight:bold;color:red;">CET UTILISATEUR À ÉTÉ BANNI POUR CE MESSAGE</span>';
            $stmt = $pdo->prepare('UPDATE '.DB_POST_TABLE.' SET message = :message WHERE id = '.$id);
            $stmt->execute(['message' => $post['message']]);
            $logged = false;
            update();
            if($post['parent'] > 0){
                updateThread($post['parent']);
            }else{
                updateThread($post['id']);
            }
            $logged = true;
        }else{
            deletePost($id);
        }

        header('Refresh: 2; URL='.ROOT.'akane.php?admin');
        serverMessage('L\'adresse '.$IP.' a été bannie');
        exit();
        
    }

    /*Lève un bannissement*/
    function liftBan($id){

        global $pdo;

        $pdo->query('DELETE FROM '.DB_BANS_TABLE.' WHERE id = '.$id);
    }

    /*Nettoyage*/
    function prune(){

        global $pdo, $logged;

        $id = $pdo->query('SELECT id FROM '.DB_POST_TABLE.' WHERE parent = 0 ORDER BY bump ASC LIMIT 1')->fetchColumn();
        deletePost($id, null, true);
    }

    /*Vérifie qu'un sujet existe*/
    function threadExist($id){

        global $pdo;

        $stmt= $pdo->prepare('SELECT id FROM '.DB_POST_TABLE.' WHERE id = '.$id.' AND parent = 0');
        $stmt->execute();
        $result = $stmt->fetch();

        return $result;
    }

    /*Fait rémonter un sujet*/
    function bump($id){

        global $pdo;

        $time = time().substr(microtime(), 2, 3);
        $stmt = $pdo->prepare('UPDATE '.DB_POST_TABLE.' SET bump = '.$time.' WHERE id = :id');
        $stmt->execute(['id' => $id]);

    }

    /*Renvoie le nombre de réponses d'un sujet*/
    function countReplies($parent){
        global $pdo;
        $req = $pdo->query('SELECT 1 FROM '.DB_POST_TABLE.' WHERE parent = '.$parent.'');
        $result = $req->fetchAll();

        return count($result);
    }

    /*Renvoie le nombre de sujets*/
    function countThreads(){
        global $pdo;
        $req = $pdo->query('SELECT 1 FROM '.DB_POST_TABLE.' WHERE parent = 0');
        $result = $req->fetchAll();

        return count($result);
    }

    /*Vérifie si le sujet est épinglé*/
    function isLocked($id){

        global $pdo;

        $stmt = $pdo->query('SELECT locked FROM '.DB_POST_TABLE.' WHERE id = '.$id);
        $result = $stmt->fetchColumn();

        return $result;
    }

    /*Vérifie si l'utilisateur est banni*/
    function isBanned($IP){

        global $pdo;

        $banned = $pdo->query('SELECT * FROM '.DB_BANS_TABLE.' WHERE IP = "'.$IP.'" LIMIT 1')->fetch();

        if($banned['expires'] < time() && $banned['expires'] > 0){
            $pdo->query('DELETE FROM '.DB_BANS_TABLE.' WHERE IP = '.$IP);
            $banned = false;
        }
        return $banned;
    }

    /*autentification*/
    function auth(){

        $logged = false;
        if(isset($_POST['login'])){
            //echo(crypt($_POST['password'], PASSWORD_SALT));die();
            if($auth = checkPassword(crypt($_POST['password'], PASSWORD_SALT))){
                $_SESSION['auth']['password'] = $auth['password'];
                $_SESSION['auth']['name'] = $auth['name'];
                $logged = true;
                return $logged;
            }
        }
        if(isset($_SESSION['auth']['password'])){
            if($auth = checkPassword($_SESSION['auth']['password'])){
                $logged = true;
                return $logged;
            }
        }
    }

    /*Autentification*/
    function checkPassword($str){

        global $pdo;

        $stmt = $pdo->prepare('SELECT * FROM '.DB_ADMINS_TABLE.' WHERE password = :password');
        $stmt->execute(['password' => $str]);
        $result = $stmt->fetch();
        
        return $result;
    }

/*-------Main-------*/

    if(isset($submit)){ //Vérifie s'il s'agit d'une publication
        if($banned = isBanned($_SERVER['REMOTE_ADDR'])){
            die(serverMessage('Erreur. Vous êtes banni.<br><small style="font-size:14pt;font-weight:normal;">Ban No.'.$banned['id'].'<br>Motif: '.$banned['reason'].'<br>Date de fin: '.($banned['expires'] > 0 ? date('d/m/y à H:i:s', $banned['expires']) : 'Jamais').'</small>'));
        }
        $cooldown = time() + COOLDOWN;
        if(!empty($upfile)){
            fileExist($upfile);
            $uploadedFileInfo = validateFile($upfile, $upfile_name);
            $thumbnail = createThumbnail($uploadedFileInfo, $parent);
        }else{
            $uploadedFileInfo = null;
        }
        if(isset($_COOKIE['cooldown'])){
            $wait = $_COOKIE['cooldown'] - time();
            die(serverMessage('Vous devez attendre '.$wait.' secondes avant de poster à nouveau.'));
        }
        if($parent > 0){ //Vérifie si c'est une réponse
            if(!threadExist($parent)){
                die(serverMessage('Le sujet N°'.$parent.' n\'existe pas'));
            }
            if($replyCount = countReplies($parent) >= MAX_REPLIES){
                die(serverMessage('Vous ne pouvez plus répondre à ce sujet'));
            }
            if(isLocked($parent)){
                die(serverMessage('Sujet verrouillé. Vous ne pouvez pas répondre.'));
            }
            $sage = $email;
            $id = processPost($parent,$name,$email,$subject,$message,$uploadedFileInfo,$password);
            if($sage != 'sage' && $replyCount <= MAX_BUMP){bump($parent);}
            updateThread($parent);
            setcookie('cooldown', $cooldown, $cooldown);
            header('Refresh: 2; URL='.ROOT.RES_FOLDER.$parent.'#'.$id);
            serverMessage('Message No.'.$id.' envoyé avec succès !');
        }else{ //sinon c'est un OP
            $id = processPost($parent,$name,$email,$subject,$message,$uploadedFileInfo,$password);
            bump($id);
            updateThread($id);
            setcookie('cooldown', $cooldown, $cooldown);
            header('Refresh: 2; URL='.ROOT.RES_FOLDER.$id);
            serverMessage('Sujet No.'.$id.' envoyé avec succès !');
        }
        update();
        catalog();
    
    }
    if(isset($_GET['admin'])){ //Accès à l'administration
        $logged = auth();
        adminView($logged);
    }
    if(isset($del)){
        deletePost($del, $password);
        header('Refresh: 2; URL='.ROOT);
        serverMessage('Message No.'.$del.' supprimé.');
    }
?>
