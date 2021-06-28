<?php

/*-----------------------------------------------------------------------------------
|                                                                                   |
|                                   Akane v2.0                                      |
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
    define('CAPCODE', [
        0 => '<span style="color:red;font-weight:bold;"> ## Administrateur</span>',
        1 => '<span style="color:purple;font-weight:bold;"> ## Moderateur</span>'
    ]);
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

//--------Connexion--------//
    /**
     * @return PDO
     */
    function getPDO(): PDO
    {
        try{
            $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';', DB_USER, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        }catch (PDOException $e){
            print_r($e);
        }
        return $pdo;
    }
//----------Models---------//
    abstract class Model
    {
        protected $pdo;
        protected $table;
        public $id;
        public $values;
        public function __construct()
        {
            $this->pdo = getPDO();
        }
        /**
         * @param string $column
         * @param mixed $value
         * @param string $order (ex : "column DESC/ASC")
         * @param integer $offset
         * @param integer $limit
         * @return mixed
         */
        public function findAll(?string $column = null, $value = null, ?string $order = null, ?int $offset = null, ?int $limit = null)
        {
            $sql = "SELECT * FROM {$this->table}";
            if($column && isset($value)){
                $sql .= " WHERE {$column} = '{$value}'";
            }
            if($order){
                $sql .= " ORDER BY {$order}";
            }
            if(isset($limit)){
                $sql .= " LIMIT {$offset}, {$limit}";
            }
            $values = $this->pdo->query($sql)->fetchAll();
            return $values;
        }
        /**
         * @param string $column
         * @param mixed $value
         */
        public function findOne(string $column, $value)
        {
            $sql = "SELECT * FROM {$this->table} WHERE {$column} = '{$value}'";
            $values = $this->pdo->query($sql)->fetch();
            return $values;
        }
        /**
         * @param string $item
         * @param string $column
         * @param mixed $value
         */
        public function find(string $item, string $column, $value)
        {
            $sql = "SELECT {$item} FROM {$this->table} WHERE {$column} = '{$value}'";
            $values = $this->pdo->query($sql)->fetchColumn();
            return $values;
        }
        /**
         * @param integer $id
         * @return mixed
         */
        public function findOneByID(int $id)
        {
            $sql = "SELECT * FROM {$this->table} WHERE id = {$id}";
            $values = $this->pdo->query($sql)->fetch();
            return $values;
        }
        /**
         * @param array $items : ['column' => 'value']
         * @return void 
         */
        public function delete(array $items): void
        {
            $sql = "DELETE FROM {$this->table} WHERE ";
            foreach($items as $key=>$item){
                $sql .= "{$key} = '{$item}', ";
            }
            $sql = substr($sql, 0, -2);
            $this->pdo->query($sql);
        }
        /**
         * @param array $values
         * @return void
         */
        public function insert(): void
        {
            $sql = "INSERT INTO {$this->table} SET ";
            foreach($this->values as $key=>$value){
                $sql .= "{$key} = '{$value}', ";
            }
            $sql = substr($sql, 0, -2);
            $this->pdo->query($sql);
            $this->id = $this->pdo->lastInsertId();
        }
        /**
         * @param string $column
         * @param string $value
         * @param string $where
         * @param string $col
         */
        public function update(string $column, string $value, string $where, string $col)
        {
            $this->pdo->query("UPDATE {$this->table} SET {$column} = '{$value}' WHERE {$where} = '{$col}'");
        }
    }
    class Board extends Model
    {
        /**
         * @return void
         */
        public function init(): void
        {
            $table = DB_POST_TABLE;
            if(!$this->pdo->query("SHOW TABLES LIKE '{$table}'")->fetch()){
                $this->pdo->query("CREATE TABLE IF NOT EXISTS `user` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `name` varchar(255) NOT NULL,
                    `rank` int(11) NULL,
                    `password` varchar(255) NOT NULL,
                    PRIMARY KEY (`id`)
                    ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
                    CREATE TABLE IF NOT EXISTS `ban` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `IP` varchar(255) NOT NULL,
                    `reason` text DEFAULT NULL,
                    `expires` int(11) NOT NULL DEFAULT '0',
                    PRIMARY KEY (`id`)
                    ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
                    CREATE TABLE IF NOT EXISTS `report` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `board` varchar(255) NOT NULL,
                    `postID` varchar(255) NOT NULL,
                    `reason` varchar(255) NOT NULL,
                    `reporterIP` varchar(255) NOT NULL,
                    `prio` int(11) NULL DEFAULT '0',
                    PRIMARY KEY (`id`)
                    ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
                    DROP TABLE IF EXISTS `{$table}` ;
                    CREATE TABLE IF NOT EXISTS `{$table}` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `date` bigint(20) NOT NULL,
                    `bump` bigint(20) NOT NULL DEFAULT '0',
                    `locked` BOOLEAN NOT NULL DEFAULT FALSE,
                    `sticky` BOOLEAN NOT NULL DEFAULT FALSE,
                    `parent` int(11) NOT NULL,
                    `name` varchar(255) NOT NULL,
                    `tripcode` varchar(255) DEFAULT NULL,
                    `IP` varchar(255) NOT NULL,
                    `email` varchar(255) DEFAULT NULL,
                    `subject` varchar(255) DEFAULT NULL,
                    `message` text,
                    `upfile_name` varchar(255) DEFAULT NULL,
                    `md5` varchar(255) DEFAULT NULL,
                    `file` varchar(255) DEFAULT NULL,
                    `thumbnail` varchar(255) DEFAULT NULL,
                    `replies` text NULL DEFAULT NULL,
                    `password` varchar(255) DEFAULT NULL,
                    PRIMARY KEY (`id`)
                    ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;");

                $page = new Page();
                $page->head(TITLE)->title()->postForm(0)->navLinks(null, 0, 0)->navLinks(null, 0, 1)->footer()->write('', 'index', 'html');
                header('Refresh: 2; URL='.ROOT);
                echo('installation terminée');
                exit();
            }
        }
    }
    class Post extends Model
    {
        protected $table = DB_POST_TABLE;
        public $date;
        public $bump;
        public $locked;
        public $sticky;
        public $parent;
        public $name;
        public $tripcode;
        public $IP;
        public $email;
        public $subject;
        public $message;
        public $upfile_name;
        public $md5;
        public $file;
        public $thumbnail;
        public $replies;
        public $password;
        public $quotes;
        /**
         * @return void
         */
        public function create(): void
        {
            $this->values = [
                'date'          => $this->date,
                'bump'          => $this->bump,
                'locked'        => $this->locked,
                'sticky'        => $this->sticky,
                'parent'        => $this->parent,
                'name'          => $this->name,
                'tripcode'      => $this->tripcode,
                'IP'            => $this->IP,
                'email'         => $this->email,
                'subject'       => $this->subject,
                'message'       => $this->message,
                'upfile_name'   => $this->upfile_name,
                'md5'           => $this->md5,
                'file'          => $this->file,
                'thumbnail'     => $this->thumbnail,
                'replies'       => $this->replies,
                'password'      => $this->password
            ];
        }
        /**
         * @param integer $id
         * @return void
         */
        public function bump(int $id): void
        {
            $bump = time().substr(microtime(), 2, 3);
            $this->pdo->query("UPDATE {$this->table} SET bump = {$bump} WHERE id = {$id}");
        }
    }
    class Ban extends Model
    {
        protected $table = "ban";
        public $IP;
        public $reason;
        public $expires;
        /**
         * @return void
         */
        public function create(): void
        {
            $this->values = [
                'IP'        => $this->IP,
                'reason'    => $this->reason,
                'expires'   => $this->expires
            ];
        }
    }
    class User extends Model
    {
        protected $table = "user";
        public $name;
        public $rank;
        public $password;
        /**
         * @return void
         */
        public function create(): void
        {
            $this->values = [
                'name'      => $this->name,
                'rank'      => $this->rank,
                'password'  => $this->password
            ];
        }
        /**
         * @return mixed
         */
        public function connect()
        {
            if(isset($_SESSION['auth']['password'])){
                $password = $_SESSION['auth']['password'];
            }elseif(isset($_POST['adminpass'])){
                $password = crypt($_POST['adminpass'], PASSWORD_SALT);
                //var_dump($password);die();
            }else{
                return false;
            }
            if($userInfo = $this->pdo->query("SELECT * FROM {$this->table} WHERE password = '{$password}'")->fetch()){
                $this->name = $userInfo['name'];
                $this->rank = $userInfo['rank'];
                $this->password = $userInfo['password'];
                $_SESSION['auth']['name'] = $userInfo['name'];
                $_SESSION['auth']['password'] = $userInfo['password'];
                return $this;
            }else{
                return false;
            }
        }
    }
    class Report extends Model
    {
        protected $table = "report";
        public $board;
        public $postID;
        public $reason;
        public $reporterIP;
        public $prio;
        /**
         * @return void
         */
        public function create(): void
        {
            $this->values = [
                'board' => $this->board,
                'postID' => $this->postID,
                'reason' => $this->reason,
                'reporterIP' => $this->reporterIP,
                'prio' => $this->prio
            ];
        }
    }
//--------Fichiers---------//
    class Page
    {
        protected $data = '';
        /**
         * Écrit une page
         * @return void
         */
        public function write(string $path, string $filename, string $ext): Page
        {
            $this->data = preg_replace('/\n|\r| ( +)/', '', $this->data);
            $output = fopen($path.$filename.'.'.$ext, 'w') or die('impossible de trouver le fichier');
            fwrite($output, $this->data);
            fclose($output);
            $this->data = '';
            return $this;
        }
        /**
         * Affiche la page en direct
         * @return void
         */
        public function render(): Page
        {
            $this->data = preg_replace('/\n|\r| ( +)/', '', $this->data);
            echo($this->data);
            $this->data = '';
            return $this;
        }
        /**
         * @param string $title
         * @return Page
         */
        public function head(string $title): Page
        {
            $this->data .= '<!DOCTYPE html>
            <html lang="fr">
            <head>
            <meta http-equiv="content-type" content="text/html;charset=UTF-8">
            <meta http-equiv="cache-control" content="max-age=0">
            <meta http-equiv="cache-control" content="no-cache">
            <meta http-equiv="expires" content="0">
            <meta http-equiv="expires" content="Tue, 01 Jan 1980 1:00:00 GMT">
            <meta http-equiv="pragma" content="no-cache">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="keywords" content="imageboard, forum, culture, japonaise,japon,anime,manga,nsfw">
            <link rel="shortcut icon" href="/img/favicon.ico">
            <title>'.$title. '</title>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script>
            function quotePost(postId){
                $("#message").val($("#message").val() + postId + "\n");
                return false;
            }
            window.addEventListener(\'DOMContentLoaded\',(event)=>{
                if(window.location.hash){
                    if(window.location.hash.match(/^#q[0-9]+$/i)!==null){
                        var postId=window.location.hash.match(/^#q[0-9]+$/i)[0].substr(2);
                        if(postId!=\'\'){
                            quotePost(\'>>\'+postId);
                        }
                    }
                }
                $(\'.backlink, .replyLink\').mouseenter(function(){
                    var num=$(this).text().substr(2);
                    var ptop=$(this).offset().top-20;
                    var pleft=$(this).offset().left;
                    $(\'body\').append(\'<div class="floatReply">\'+$(\'#\'+num).html()+\'</div>\');
                    $(\'.floatReply\').css({position: \'absolute\',top:(ptop-$(\'.floatReply\').height()),left:pleft+40});
                }).mouseleave(function(){
                    $(\'.floatReply\').remove();
                });
                $(\'form img\').mouseenter(function(){
                    var src=$(this).parent(\'a\').attr(\'href\');
                    $(\'html\').append(\'<div class="imgLarge" style="position:fixed;top:0;right:0;height:100vh;z-index:12;"><img style="top:0;right:0;max-height:100%;max-width:60vw;" src="\'+src+\'"></div>\');
                }).mouseleave(function(){
                    $(\'.imgLarge\').remove();
                });
            });
            </script>
            <style>
            html{height:100%;scroll-behavior: smooth;}
            html,body{background:#d3d3d3;}
            body{font-family:Arial;font-size:10pt;margin:8px;padding-top:170px;}
            footer{font-size:8pt;}
            textarea {
                font-family: Arial;
                font-size: 10pt;
            }
            .logo{background: #951818;;
                width: 100vw;
                height:106px;
                margin-left: -8px;
                margin-top: -8px;
                position: fixed;
                top: 0;
                padding:8px}
            .logo img{width:96%;max-width:600px;max-height:7em;}
            .postform{text-align:left;width:100%;}
            .postform ul{font-size:8pt;font-weight:normal;padding:0;margin:0;margin-left:8px;}
            #move {
                z-index: 9;
              }
              
              #moveheader {
                cursor: move;
                z-index: 10;
              }
            .doc{width:100%;max-width:750px;margin:auto;}
            .box{background-color:#FFF;}
            .boxtitle{background:#a2a2a2;color:#FFFFFF;margin:0;font-size:16pt;font-weight:bold;padding-left:8px;}
            .boxcontent{padding:8px;}
            .boardtype{font-weight:bold;}
            .lastsubjects{width:100%;border:1px solid #cacaca;border-spacing:0;}
            hr{clear:both;}
            a{color:black;}
            a.anchor {
                display: block;
                position: relative;
                top: -300px;
                visibility: hidden;
            }
            a:hover{color:red;}
            button{color: white;
                background: #2d2d2d;
                border-radius: 4px;
                border: none;}
            button a{color:white;text-decoration:none;}
            button a:hover{color:white;}
            button:hover{cursor:pointer;box-shadow:0px 0px 1px 1px #888;}
            .paginate{background:#b5b5b5;padding:8px;padding-left:16px;}
            .paginate button{font-size:14pt;font-weight:bold;}
            .postStatus{color:red;font-weight:bold;}
            .postImg{float:left}
            .counter{float:left;color:#9b9b9b;font-family:Courier;}
            .reply, .floatReply{background:#ffffff;box-shadow:0px 0px 1px #a8a8a8;padding:0;padding-bottom:8px;}
            .floatReply img{margin-right:12px;}
            .reply:target{background-color:#bfbfbf;scroll-margin-top: 170px;}
            .replyLink{color:red;text-decoration:underline;}
            .replyhead{font-size:10pt;margin: 0;padding:8px;background:#ddd;}
            .replybody{padding: 8px;}
            .backlink{font-size:10pt;text-decoration:underline;}
            .quote{color:#789922;}
            form a{text-decoration:none;}
            form img{margin-right:12px;}
            .navlinks{clear: both;
                padding: 8px;
                margin-top: -3px;
                background: #2d2d2d;
                width: 100vw;
                margin-left: -8px;
                position: fixed;
                top: 8em;
                padding-left:16px;}
            .navlinks button{font-size:14pt;font-weight:bold;margin-right:8px;}
            .useractions{float:right;padding-right:36px;}
            .OPImg{margin-bottom:8px;float:left;}
            .catalogrow{cursor:pointer;}
            .cataloglabel{background:#484848;color:white;font-weight:bold;text-align:left;}
            .catalogrow:hover{background-color:#c2c2c2;}
            .subject{color:#cc1105;font-size:16px;font-weight:bold;}.name{color:#0052aa;font-weight:bold;}
            .tripcode{color:#228854;}
            @media only screen and (max-width: 480px){
                iframe{width:150px;height:84px;}
                .counter{display:none;}
                .boardname h{font-size:14pt;}
                .logo{height:5em}
                .navlinks{top:5em}
                .navlinks button{font-size:8pt;margin-bottom:8px;}
                .useractions{font-size:10pt;}
            }
            @media only screen and (min-width:800px){
                #move{position:fixed;}
            }
            </style>
            </head>
            <body>
            <a class="anchor" id="up"></a>';
            return $this;
        }
        /**
         * @return Page
         */
        public function title(?User $user = null): Page
        {
            $this->data .= ($user ? '<div>[<span style="color:red;font-weight:bold;">Connecté: '.$user->name.'</span>]&nbsp;
            [<a href="akane.php?admin&logout">Se déconnecter</a>]&nbsp;
            [<a href="akane.php?admin&banlist">Liste des bannis</a>]&nbsp;
            [<a href="akane.php?admin&reportlist">Signalements</a>]&nbsp;
            [<a href="akane.php?admin&rebuildall">Tout reconstruire</a>]</div>': '').'
            <div class="logo">
            <img src="'.LOGO.'">
            </div>
            <div class="boardname"><hr style="max-width:750px">
            <h1 align="center">'.TITLE.'</h1>
            </div>';
            return $this;
        }
        /**
         * @param integer $parent
         * @return Page
         */
        public function postForm($parent): Page
        {
            $this->data .= '<div id="move" class="box" style="left:50vw;top:50vh;">
            <div id="moveheader" class="boxtitle">
            '.(!$parent ? 'Créer un nouveau sujet' : 'Répondre au sujet No.'.$parent).'
            </div>
            <form action="'.ROOT.'akane.php" method="post" enctype="multipart/form-data"><input type="hidden" name="parent" value="'.(!$parent ? '0' : $parent). '">
            <table class="postform">
            <tr><td><input type="text" placeholder="Nom (facultatif)" name="name" style="width: -moz-available;"></td></tr>
            <tr><td><input type="text" placeholder="E-mail (facultatif)" name="email" style="width: -moz-available;"></td></tr>';
            if(!$parent){$this->data .= '
                <tr><td><input type="text" placeholder="Sujet (facultatif)" name="subject" style="width: -moz-available;"></td></tr>';
            }
            $this->data .= '
            <tr><td><textarea id="message" placeholder="Message" name="message" max="8000" style="width: -moz-available;height:80px;"></textarea></td></tr>';
            if(UPLOADS){$this->data .= '
                <tr><td><input type="file" name="upfile"></td></tr>';
            }
            $this->data .= '
            <tr><td><input type="password" placeholder="Mot de passe (pour supprimer)" name="password" style="width: -moz-available;"></td></tr>
            <tr><td><input type="submit" name="newpost" value="Envoyer"></td></tr><tr>
            <th colspan="2">
            <ul>
                <li>'.(UPLOADS ? 'Il faut au moins une image ou du texte pour répondre.' : 'Il faut au moins un message pour répondre.').'</li>
                '.(UPLOADS ? '<li>Les formats supportés sont JPG, PNG et GIF.</li><li>Taille maximale du fichier: 3Mo.</li>' : '').
                (VIDEO ? '<li>Un lecteur vidéo sera généré s\'il y a un lien</li><li>Plate-formes supportées: Youtube.</li>' : '').'
            </ul>
            </th>
            </tr>
            </table>
            </form>
            </div>
            <script>
            dragElement(document.getElementById("move"));

            function dragElement(elmnt) {
            var pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;
            if (document.getElementById(elmnt.id + "header")) {
                document.getElementById(elmnt.id + "header").onmousedown = dragMouseDown;
            } else {
                elmnt.onmousedown = dragMouseDown;
            }

            function dragMouseDown(e) {
                e = e || window.event;
                e.preventDefault();
                pos3 = e.clientX;
                pos4 = e.clientY;
                document.onmouseup = closeDragElement;
                document.onmousemove = elementDrag;
            }

            function elementDrag(e) {
                e = e || window.event;
                e.preventDefault();
                pos1 = pos3 - e.clientX;
                pos2 = pos4 - e.clientY;
                pos3 = e.clientX;
                pos4 = e.clientY;
                elmnt.style.top = (elmnt.offsetTop - pos2) + "px";
                elmnt.style.left = (elmnt.offsetLeft - pos1) + "px";
            }

            function closeDragElement() {
                document.onmouseup = null;
                document.onmousemove = null;
            }
            }
            </script>';
            return $this;
        }
        /**
         * @param bool $logged
         * @param integer $parent
         * @param bool $position : 0 = en haut, 1 = en bas
         * @return Page
         */
        public function navLinks(?User $user, int $parent, bool $position): Page
        {
            if(!$position){
                $this->data .= '<form action="'.ROOT.'akane.php" method="get">
                <hr>
                <div class="navlinks">
                <span><button><a href="/">Accueil</a></button>' .
                ($parent ? '<button><a href="' .
                ($user ? 'akane.php?admin&page=0' : ROOT) . '">Index</a></button>' : '') . '
                <button type="button"><a href="' . ROOT . 'catalogue">Catalogue</a></button>
                <button type="button"><a href="#down">&#9660;</a></button>
                <button type="button"><a href="#up">&#9650;</a></button>
                <button onclick="window.location.reload();" type="button">Rafraîchir</button>
                </span>
                <span class="useractions">
                <input type="password" placeholder="Mot de passe" name="delpassword" size="8">
                <input type="submit" name="deletepost" value="Supprimer"><input type="submit" name="report" value="Signaler">
                </span>
                </div>';
            }else{
                $this->data .= '</form>';
            }
            
            return $this;
        }
        /**
         * @param array $OP
         * @param bool $logged
         * @param bool $index
         * @return Page
         */
        public function OP(array $OP, ?User $user, bool $index): Page
        {
            $this->data .= '<div id="'.$OP['id'].'" style="scroll-margin-top: 170px;'.($user ? 'border: 4px solid hsl('.(hexdec(substr(md5($OP['IP']), 0, 2))).',100%,75%);' : '').'">';
            if(UPLOADS && !empty($OP['file'])){
                $this->data .= 'Fichier:<a href="'.ROOT.IMG_FOLDER.$OP['file'].'" target="_blank">'.(strlen($OP['upfile_name']) > 20 ? substr($OP['upfile_name'], 0, 20).'...'.substr($OP['upfile_name'], -4) : $OP['upfile_name']).'</a>
                [<a target="_blank" href="https://saucenao.com/search.php?url=https://www.akane-ch.org'.ROOT.THUMB_FOLDER.$OP['thumbnail'].'">SauceNao</a>]<br>
                <a href="'.ROOT.IMG_FOLDER.$OP['file'].'" target="_blank">
                <img class="OPImg" src="'.ROOT.THUMB_FOLDER.$OP['thumbnail'].'" title="'.$OP['upfile_name'].'" alt="'.$OP['upfile_name'].'"></a>';
            }
            $this->data .= '<input type="checkbox" name="del" value="'.$OP['id'].'">'.(!empty($OP['subject']) ? '
            <span class="subject">'.$OP['subject'].'</span>&nbsp;' : '').'
            <span class="name">'.(!empty($OP['email']) ? '<a href="mailto:'.$OP['email'].'">'.$OP['name'].'</a>' : $OP['name']).'</span>
            '.(!empty($OP['tripcode']) ? '
            <span class="tripcode">'.$OP['tripcode'].'</span>' : '').'&nbsp;
            <span>'.date('d/m/y \à H:i:s', $OP['date']).'</span>&nbsp;
            <span><a href="'.ROOT.RES_FOLDER.$OP['id'].'#'.$OP['id'].'">No.</a>
            <a href="'.ROOT.RES_FOLDER.$OP['id'].'#q'.$OP['id'].'"'.(!$index ? ' onclick="javascript:quotePost(\'>>'.$OP['id'].'\')"' : null).'>'.$OP['id'].'</a>
            '.($OP['locked'] ? '&nbsp;
            [<span class="postStatus">Verrouillé</span>]' : '').
            ($OP['sticky'] ? '&nbsp;
            [<span class="postStatus">Épinglé</span>]' : '').
            ($index ? '&nbsp;
            <button><a href="'.($user ? 'akane.php?admin&res='.$OP['id'] : ROOT.RES_FOLDER.$OP['id']).'">Répondre</a></button>' : '').
            ($user ? '&nbsp;
            ['.$OP['IP'].']&nbsp;
            [<a href="akane.php?admin&admindel='.$OP['id'].'">Supprimer</a>]&nbsp;
            [<a href="akane.php?admin&ban='.$OP['id'].'">Bannir</a>]&nbsp;
            '.($OP['locked'] ? '
            [<a href="akane.php?admin&unlock='.$OP['id'].'">Déverrouiller</a>]&nbsp;' : '
            [<a href="akane.php?admin&lock='.$OP['id'].'">Verrouiller</a>]&nbsp;').
            (!$OP['sticky'] ? '
            [<a href="akane.php?admin&stick='.$OP['id'].'">Épingler</a>]&nbsp;
            ' : '[<a href="akane.php?admin&unstick='.$OP['id'].'">Désépingler</a>]&nbsp;
            ') : '').'
            '.$OP['replies'].'</span><br><p>'.
            $OP['message'].'</p><br></div>';
            return $this;
        }
        /**
         * @param integer $parent
         * @param bool $logged
         * @param bool $index
         * @param bool ?$hrTag 
         * @return Page
         */
        public function replies(int $parent, ?User $user = null, bool $index, ?bool $hrTag = false): Page
        {
            $item = new Post();
            $replies = $item->findAll('parent', $parent, 'id DESC');
            if($index){
                $replyCount = count($replies);
                $hidden = $replyCount - INDEX_PREVIEW;
                if($replyCount > INDEX_PREVIEW){
                    $this->data .= '<span style="color:grey;">'.$hidden.' réponses omises, cliquez sur \'Répondre\' pour tout voir.</span>';
                }
            }else{
                $hidden = 0;
            }
            $counter = 0;
            foreach(array_reverse($replies) as $reply){
                $this->data .= '<table style="margin-top:6px;'.($counter < $hidden ? 'display:none;' : '').'">
                <tr><td class="counter" '.($counter >= MAX_BUMP ? 'style="color:orange;" alt="Le sujet ne remontera plus"' : '').'>'.sprintf('%03d', ($counter + 1)). '</td>
                <td id="'.$reply['id'].'" class="reply anchor" '.($user ? 'style="border: 4px solid hsl('.(hexdec(substr(md5($reply['IP']), 0, 2))).',100%,75%);"' : '').'>
                <div class="replyhead">
                <input type="checkbox" name="del" value="'.$reply['id'].'">
                <span class="name">'.(!empty($reply['email']) ? '<a href="mailto:'.$reply['email'].'">'.$reply['name'].'</a>' : $reply['name']).'</span>&nbsp;
                '.(!empty($reply['tripcode']) ? '<span class="tripcode">'.$reply['tripcode'].'</span>&nbsp;' : '').'
                <span>'.date('d/m/y \à H:i:s', $reply['date']).'</span>&nbsp;
                <span><a href="'.ROOT.RES_FOLDER.$reply['parent'].'#'.$reply['id'].'">No.</a>
                <a href="'.ROOT.RES_FOLDER.$reply['parent'].'#q'.$reply['id'].'" '.(!$index ? 'onclick="javascript:quotePost(\'>>'.$reply['id'].'\')"' : '').'>'.$reply['id'].'</a>'.
                ($user ? '&nbsp;['.$reply['IP'].']&nbsp;
                [<a href="akane.php?admin&admindel='.$reply['id'].'">Supprimer</a>]&nbsp;
                [<a href="akane.php?admin&ban='.$reply['id'].'">Bannir</a>]' : '').' '.$reply['replies'].'</span><br>';
                if(UPLOADS && !empty($reply['file'])){
                    $this->data .= 'Fichier:<a href="'.ROOT.IMG_FOLDER.$reply['file'].'" target="_blank">'.(strlen($reply['upfile_name']) > 20 ? substr($reply['upfile_name'], 0, 20).'...'.substr($reply['upfile_name'], -4) : $reply['upfile_name']).'</a>
                    [<a target="_blank" href="https://saucenao.com/search.php?url=https://www.akane-ch.org'.ROOT.THUMB_FOLDER.$reply['thumbnail'].'">SauceNao</a>]</div>
                    <div class="replybody">
                    <a href="'.ROOT.IMG_FOLDER.$reply['file'].'" target="_blank"><img class="postImg" src="'.ROOT.THUMB_FOLDER.$reply['thumbnail'].'" title="'.$reply['upfile_name'].'" alt="'.$reply['upfile_name'].'"></a>';
                }else{
                    $this->data .= '</div><div class="replybody">';
                }
                $this->data .= '<p>'.$reply['message'].'</p>
                </div>
                </td>
                </tr>
                </table>';
                $counter++;
            }
            if($hrTag){
                $this->data .= '<hr>';
            }
            return $this;
        }
        /**
         * @return Page
         */
        public function catalog(): Page
        {
            $item = new Post();
            $posts = $item->findAll('parent', 0, 'sticky DESC, bump DESC');
            $this->data .= '
        <div class="navlinks">
            <a href="/"><button>Accueil</button></a>
            <a href="'.ROOT. '"><button>Index</button></a>
            <a href="'.ROOT.'catalogue" data-refresh=".box"><button>Rafraîchir</button></a>
        </div>
        <div class="box" style="margin:auto;max-width:750px;">
        <div class="boxtitle">
            Catalogue
        </div>
        <div class="boxcontent">
            <table class="lastsubjects">
                <thead>
                    <th class="cataloglabel">No.</th>
                    <th class="cataloglabel">Sujet/Message</th>
                    <th class="cataloglabel">Auteur</th>
                    <th class="cataloglabel">Réponses</th>
                </thead>';
            foreach($posts as $post){
                $numReplies = count($item->findAll('parent', $post['id']));
                $this->data .= '
                <tr class="catalogrow" onclick="window.open(\''.ROOT.RES_FOLDER.$post['id'].'\')">
                    <td>'.$post['id'].'</td>
                    <td style="line-break:anywhere;">'.($post['subject'] ? '<strong>'.substr($post['subject'], 0, 80).'</strong><br>' : '').substr(strip_tags($post['message']), 0, 80).'</td>
                    <td>'.$post['name'].'</td>
                    <td>'.$numReplies.'</td>
                </tr>';
            }
            $this->data .= '</table></div></div>';
            return $this;
        }
        /**
         * @return Page
         */
        public function auth(): Page
        {
            $this->data .= '<div align="center">
            <h2>Administration</h2>
            <form action="akane.php?admin" method="post">
            <label>Mot de passe</label>
            <input type="password" name="adminpass">
            <input type="submit" name="login" value="Connexion">
            </form>
            </div>
            </body>
            </html>';
            return $this;
        }
        /**
         * @return Page
         */
        public function banList(): Page
        {
            $item = new Ban();
            $posts = $item->findAll(null , null, 'id DESC');
            $this->data .= '<div class="doc">
                <div class="box">
                <div class="boxtitle">
                Liste des bannis
                </div>
                <div class="boxcontent">
                <table class="lastsubjects" style="width:100%;">
                <thead>
                    <th class="cataloglabel">Identifiant No.</th>
                    <th class="cataloglabel">Adresse IP</th>
                    <th class="cataloglabel">Motif</th>
                    <th class="cataloglabel"Date d\'expiration</th>
                    <th class="cataloglabel">Action</th>
                </thead>';
            foreach($posts as $post){
                $this->data .= '<tr class="catalogrow">
                    <td>'.$post['id'].'</td>
                    <td>'.$post['IP'].'</td>
                    <td>'.$post['reason'].'</td>
                    <td>'.date('d/m/Y à H:i:s', $post['expires']).'</td>
                    <td><a href="akane.php?admin&liftban='.$post['id'].'">Lever</a></td>
                </tr>';
            }
            $this->data .= '</table>
                </div>
                </div>
                </div>';
            return $this;
        }
        /**
         * @param int $id
         * @return Page
         */
        public function banForm(int $id)
        {
            $item = new Post();
            $post = $item->findOne('id', $id);
            $this->data .= '<div class="box">
                <div class="boxtitle">
                Registrer un ban
                </div>
                <div class="boxcontent">
                <form action="akane.php?admin&ban" method="post">
                    <input type="hidden" name="IP" value="'.$post['IP'].'">
                    <input type="hidden" name="id" value="'.$post['id'].'">
                    <table class="lastsubjects" style="width:100%;">
                        <thead>
                            <th class="cataloglabel">IP</th>
                            <th class="cataloglabel">Post No.</th>';
            if(!empty($post['thumbnail'])){
                $this->data .= '<th class="cataloglabel">Image</th>';
            }
            $this->data .= '<th class="cataloglabel">Message</th>
                <th class="cataloglabel">Raison</th>
                <th class="cataloglabel">Durée</th>
                <th class="cataloglabel">Public ?</th>
                <th class="cataloglabel">Action</th>
            </thead>
            <tr>
                <td>'.$post['IP'].'</td>
                <td>'.$post['id'].'</td>';
            if(!empty($post['thumbnail'])){
                $this->data .= '<td><img src="'.ROOT.THUMB_FOLDER.$post['thumbnail'].'" height="124"></td>';
            }
            $this->data .= '<td>'.strip_tags($post['message']).'</td>
                    <td>
                        <select name="reason">
                            <option value="Contenu illégal.">Contenu illégal</option>
                            <option value="Message troll.">Troll</option>
                            <option value="Shitpost.">Shitpost</option>
                            <option value="Spam">Spam</option>
                            <option value="Signalement abusif">Signalement abusif</option>
                            <option value="Contenu NSFW en dehors des forums NSFW">NSFW</option>
                            <option value="Partage d\'infos personnelles">Infos persos</option>
                            <option value="Pas de pub à but lucratif.">Publicité lucrative</option>
                        </select>
                    </td>
                    <td>
                        <select name="expires">
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
                    <td><input type="checkbox" name="publicban"></td>
                    <td><input type="submit" name="submitban" value="Bannir"></td>
                    </tr>
                </table>
            </form>
            </div>
            </div>';
            return $this;
        }
        /**
         * @return Page
         */
        public function reportList(): Page
        {
            $item = new Report();
            $posts = $item->findAll(null , null, 'prio DESC, id DESC');
            $this->data .= '<div class="doc">
            <div class="box">
            <div class="boxtitle">
            Signalements
            </div>
            <div class="boxcontent">
            <table class="lastsubjects" style="width:100%;">
            <thead>
                <th class="cataloglabel">Signalement No.</th>
                <th class="cataloglabel">Prioritée</th>
                <th class="cataloglabel">Message No.</th>
                <th class="cataloglabel">Raison</th>
                <th class="cataloglabel">Signalé par</th>
                <th class="cataloglabel">Action</th>
            </thead>';
            foreach($posts as $post){
                $this->data .= '<tr class="catalogrow">
                    <td>'.$post['id'].'</td>
                    <td>'.$post['prio'].'</td>
                    <td><a href="'.$post['board'].RES_FOLDER.$post['postID'].'">'.$post['board'].' - '.$post['postID'].'</a></td>
                    <td>'.$post['reason'].'</td>
                    <td>'.$post['reporterIP'].'</td>
                    <td><a href="'.ROOT.'akane.php?admin&delreport='.$post['id'].'">Effacer</a></td>
                </tr>';
            }
            $this->data .= '</table>
                </div>
                </div>
                </div>';
            return $this;
        }
        /**
         * @param bool $prev
         * @param bool $next
         * @param integer $page
         * @return Page
         */
        public function paginate(bool $prev, bool $next, int $page): Page
        {
            $this->data .= '<div class="paginate">';
            if($prev){
                $this->data .= '<a href="'.ROOT.(($page - 1) > 1 ? $page - 1 : '').'"><button>Précédent</button></a>';
            }
            if($next){
                $this->data .= '<a href="'.ROOT.($page + 1).'"><button>Suivant</button></a>';
            }
            $this->data .= '</div>';
            return $this;
        }
        /**
         * @return Page
         */
        public function footer(): Page
        {
            $this->data.='</form><hr>
            <footer id="down"><p style="text-align:center">
            - Akane version 2.0 | &copy;2021-'.date('Y', time()).' Akane-ch.org | <a href="/cgu">CGU</a> -<br>
            - Les utilisateurs seuls sont responsables des messages et images qu\'ils envoient. Akane-ch.org, ainsi que son administration, se dégagent de toute opinion ou intention qui pourrait y être exprimée. -</p>
            </footer></body></html>';
            return $this;
        }
        /**
         * @param integer $id
         * @return Page
         */
        public function report(int $id): Page
        {
            $this->data .= '<div align="center">
            <h2>Signaler un message</h2>
            <form action="akane.php?report&del='.$id.'" method="post">
            <input type="hidden" name="id" value="'.$id.'">
            <label>Motif: </label>
            <select name="reason">
                <option value="Contenu illégal.">Contenu illégal</option>
                <option value="Message troll.">Troll</option>
                <option value="Shitpost.">Shitpost</option>
                <option value="Spam">Spam</option>
                <option value="Contenu NSFW en dehors des forums NSFW">NSFW</option>
                <option value="Partage d\'infos personnelles">Infos persos</option>
                <option value="Pas de pub à but lucratif.">Publicité lucrative</option>
            </select>
            <input type="submit" name="report" value="Signaler">
            </form>
            </div>
            </body>
            </html>';
            return $this;
        }
    }
    class Image
    {
        protected $data = '';
        public $thumbnail;
        public $md5;
        /**
         * @param string upfile : $_FILES tmp_name
         * @param string upfile_name : $_FILES name
         * @return Image
         */
        public function validate(string $upfile, string $upfile_name): Image
        {
            $this->upfile = $upfile;
            $this->upfile_name = $upfile_name;
            $this->path = IMG_FOLDER;
            if($_FILES['upfile']['size'] > MAX_FILESIZE){
                die('Fichier trop grand');
            }
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            if (false === $this->ext = array_search($finfo->file($this->upfile), FILE_TYPES, true)) {
                die('Format incorrect');
            }
            $this->md5 = md5_file($upfile);
            $this->filename = time().substr(microtime(), 2, 3);
            if (!move_uploaded_file($this->upfile, IMG_FOLDER.$this->filename.'.'.$this->ext)) {
                die('Erreur lors du transfert');
            }
            return $this;
        }
        /**
         * @return Image
         */
        public function thumbnail(bool $parent): Image
        {
            $fname = IMG_FOLDER.$this->filename.'.'.$this->ext;
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
                    if(!$im_in){return 1;}
                    break;
                case 2 : 
                    $im_in = @ImageCreateFromJPEG($fname);
                    if(!$im_in){return 2;}
                    break;
                case 3 :
                    $im_in = @ImageCreateFromPNG($fname);
                    break;
                default : return 3;
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
            $this->thumbnail = $this->filename.'s.'.$this->ext;
            ImageJPEG($im_out, THUMB_FOLDER . $this->thumbnail, 100);
            imagedestroy($im_in);
            imagedestroy($im_out);
            return $this;
        }
    }
//------Controlleurs-------//
    class BoardController
    {
        /**
         * @param bool $logged
         * @param integer $pageNum
         * @return void
         */
        static function update(?User $user = null, ?int $pageNum = 0): void
        {
            $post = new Post();
            $page = new Page();
            if($user){
                $maxPages = $pageNum+1;
            }else{
                $maxPages = MAX_PAGES;
            }
            for($i = $pageNum; $i < $maxPages; $i++){ //Génération des pages à l'index
                $indexThreads = $post->findAll('parent', 0, 'sticky DESC, bump DESC', $i * THREADS_PER_PAGE, THREADS_PER_PAGE);
                $page
                    ->head(($i > 0 ? ROOT.' - page '.($i+1): TITLE))
                    ->title($user)
                    ->postForm(0)
                    ->navLinks($user, 0, 0);
                foreach($indexThreads as $indexThread){
                    if($indexThread !== end($indexThreads)){$hrTag = true;}else{$hrTag = false;}
                    $page
                    ->OP($indexThread, $user, true)
                    ->replies($indexThread['id'], $user, true, $hrTag);
                }
                $page
                    ->navLinks($user, 0, 1)
                    ->paginate(($i > 0 ? 1 : 0), (count($indexThreads) < THREADS_PER_PAGE ? 0 : 1), $i+1)
                    ->footer();
                if($user){
                    $page->render();
                }else{
                    $page->write('', ($i==0?'index':$i+1), 'html');
                }
                if(!$indexThreads){break;}
            }
            if(!$user){
                $page
                ->head(ROOT.' - Catalogue')
                ->title()
                ->catalog()
                ->footer()
                ->write('', 'catalogue', 'html');
            }
        }
        /**
         * @param integer $id : Le parent sera trouvé automatiquement
         * @return void
         */
        static function updateThread(int $id)
        {
            $item = new Post();
            $page = new Page();
            $post = $item->findOne('id', $id);
            $OP = ($post['parent'] == 0 ? $post : $item->findOne('id', $post['parent']));
            $title = ROOT . ' - ' . (!empty($OP['subject']) ? $OP['subject'] : substr(strip_tags($OP['message']), 0, 30));
            $parent = $OP['id'];
            $page
                ->head($title)
                ->title()
                ->navLinks(null, $parent, 0)
                ->OP($OP, null, false)
                ->replies($parent, null, false)
                ->navLinks(null, $parent, 1)
                ->postForm($parent)
                ->footer()
                ->write(RES_FOLDER, $parent, 'html');
        }
        /**
         * @return void
         */
        static function rebuildAll(): void
        {
            $post = new Post();
            $page = new Page();
            $OPs = $post->findAll('parent', 0);
            foreach($OPs as $OP){
                $title = ROOT.' - '.(!empty($OP['subject']) ? $OP['subject'] : substr(strip_tags($OP['message']), 0, 30));
                $parent = $OP['id'];
                $page
                ->head($title)
                ->title()
                ->navLinks(null, $parent, 0)
                ->OP($OP, null, false)
                ->replies($parent, null, false)
                ->navLinks(null , $parent, 1)
                ->postForm($parent)
                ->footer()
                ->write(RES_FOLDER, $parent, 'html');
            }
            BoardController::update();
        }
        /**
         * @return void
         */
        static function prune(){
            $post = new Post();
            $totalOPs = $post->findAll('parent', 0, 'sticky ASC, bump ASC');
            if(count($totalOPs) > MAX_PAGES * THREADS_PER_PAGE){
                $post->delete(['id'], $totalOPs[0]['id']);
            }
        }
    }
    class PostController
    {
        static function processPost()
        {
            if(isset($_COOKIE['cooldown'])){
                $wait = $_COOKIE['cooldown'] - time();
                die('Vous devez attendre '.$wait.' secondes avant de poster à nouveau.');
            }
            $ban = new Ban();
            if($banned = $ban->findOne('IP', $_SERVER['REMOTE_ADDR'])){
                if($banned['expires'] == 0 || $banned['expires'] < time()){
                    die('Erreur. Vous êtes banni.<br><small style="font-size:14pt;font-weight:normal;">Ban No.'.$banned['id'].'<br>Motif: '.$banned['reason'].'<br>Date de fin: '.($banned['expires'] > 0 ? date('d/m/y à H:i:s', $banned['expires']) : 'Jamais').'</small>');
                }else{
                    $ban->delete(['id' => $banned['id']]);
                }
            }
            $cooldown = time() + COOLDOWN;
            $post = new Post();
            $page = new Page();
            if(UPLOADS && $_FILES['upfile']['error'] == 0){ //Validation du fichier
                $upfile_name= $_FILES['upfile']['name'];
                $upfile= $_FILES['upfile']['tmp_name'];
                $image = new Image();
                $image->validate($upfile, $upfile_name);
                $image->thumbnail($_POST['parent']);
            }
            $postRequest = [
                'parent' => intval($_POST['parent']),
                'name' => $_POST['name'],
                'email' => $_POST['email'],
                'subject' => (isset($_POST['subject']) ? $_POST['subject'] : '' ),
                'message' => $_POST['message'],
                'password' => $_POST['password'],
                'upfile_name' => (isset($upfile_name) ? $upfile_name : '')
            ];
            $validPost = PostController::validate($postRequest, isset($image) ? $image : null); //Validation et création d'un objet Post
            if($validPost->parent > 0){
                $OP = $post->findOneByID($validPost->parent);
                $replyCount = count($post->findAll('parent', $validPost->parent));
                if($OP['locked'] == true || $replyCount >= MAX_REPLIES){
                    die('Ce sujet est verrouillé, vous ne pouvez plus répondre');
                }
                $title = ROOT.' - '.(!empty($OP['subject']) ? $OP['subject'] : substr(strip_tags($OP['message']), 0, 30));
                $parent = $validPost->parent;
            }else{
                $replyCount = 0;
                $OP = $post->findOneByID($validPost->id);
                $title = ROOT.' - '.(!empty($validPost->subject) ? $validPost->subject : substr(strip_tags($validPost->message), 0, 30));
                $parent = $validPost->id;
                BoardController::prune();
            }
            if($postRequest['email'] !== 'sage' || $replyCount < MAX_BUMP){
                $validPost->bump($parent);
            }
            $validPost->create();
            $validPost->insert();
            PostController::bindQuotes($validPost->quotes, $validPost->id, $validPost->parent);
            if($replyCount >= MAX_REPLIES){
                $post->update('locked', 1, 'id', $OP['id']);
            }
            $page
                ->head($title)
                ->title()
                ->navLinks(null, $parent, 0)
                ->OP($OP, null, false)
                ->replies($parent, null, false)
                ->navLinks(null, $parent, 1)
                ->postForm($parent)
                ->footer()
                ->write(RES_FOLDER, $parent, 'html');
            BoardController::update();
            setcookie('cooldown', $cooldown, $cooldown);
            header('Location: '.ROOT.RES_FOLDER.$parent.'#'.$validPost->id);
        }
        /**
         * @param integer $id
         * @return void
         */
        static function deletePost(int $id): void
        {
            $post = new Post();
            $delPost = $post->findOneByID($id);
                $post->delete(['id' => $delPost['id']]);
                @unlink(IMG_FOLDER.$delPost['file']);
                @unlink(THUMB_FOLDER.$delPost['thumbnail']);
                if($delPost['parent'] == 0){
                    $replies = $post->findAll('parent', $delPost['id']);
                    foreach($replies as $reply){
                        @unlink(IMG_FOLDER.$reply['file']);
                        @unlink(THUMB_FOLDER.$reply['thumbnail']);
                    }
                    @unlink(RES_FOLDER.$delPost['id'].'.html');
                    $post->delete(['parent' => $delPost['id']]);
                }
        }
        /**
         * @return void
         */
        static function userDelete()
        {
            $post = new Post();
            $page = new Page();
            if($delPost = $post->findOneByID($_GET['del'])){
                if(password_verify($_GET['delpassword'], $delPost['password'])){
                    PostController::deletePost($delPost['id']);
                    BoardController::updateThread($_GET['del']);
                    BoardController::update();
                }else{
                    die('mot de passe incorrect');
                }
            }else{
                die('aucun message avec cet identifiant');
            }
        }
        /**
         * @return void
         */
        static function userReport()
        {
            $page = new Page();
            $post = new Post();
            if(isset($_POST['report']) && $reportPost = $post->findOneByID($_POST['id'])){
                $report = new Report();
                $checkIP = $report->find('reporterIP', 'postID', $reportPost['parent'].'#'.$reportPost['id']);
                if($_SERVER['REMOTE_ADDR'] == $checkIP){
                    die('Vous avez déjà signalé ce message');
                }else{
                    $report->board = ROOT;
                    $report->postID = ($reportPost['parent'] > 0 ? $reportPost['parent']."#" : null ).$reportPost['id'];
                    $report->reason = $_POST['reason'];
                    $report->reporterIP = $_SERVER['REMOTE_ADDR'];
                    $report->prio = 0;
                    $report->create();
                    $report->insert();
                    if($findReports = $report->findOne('postID', $report->postID)){
                        $prio = $findReports['prio'];
                        $report->update('prio', $prio + 1, 'postID', $report->postID);
                    }
                    header('Refresh: 2; URL='.ROOT);
                    echo 'contenu signalé';
                }
            }else{
                $page->head('Signalement')->report($_GET['del'])->render();
            }
        }
        /**
         * @param array $post
         * @return Post
         */
        static function validate(array $post, Image $image = null): Post
        {
            if(UPLOADS && $post['parent'] == 0 && !$image){
                die('Il faut une image pour démarrer un sujet.');
            }
            if($post['parent'] > 0 && empty($post['message'])){
                if(!$image){
                    die('Il faut au moins une image ou un message pour répondre');
                }
            }
            $validPost = new Post();
            $validPost->date        = time();
            $validPost->bump        = 0;
            $validPost->locked      = 0;
            $validPost->sticky      = 0;
            $validPost->parent      = intval($post['parent']);
            $validPost->name        = !empty($post['name']) ? htmlspecialchars(substr($post['name'], 0, 40)) : ANON_NAME;
            if(preg_match('/^(.*)##(.*)$/', $validPost->name, $match)){
                $user = new User();
                if($admin = $user->connect()){
                    $validPost->name = $match[1].CAPCODE[$admin->rank];
                }else{
                    die('Échec de l\'authentification');
                }
                $validPost->tripcode = null;
            }elseif(preg_match('/^(.*)#(.*)$/', $validPost->name, $match)){
                $validPost->name = $match[1];
                $validPost->tripcode = '!'.substr(crypt($match[2], PASSWORD_SALT), -10);
            }else{
                $validPost->tripcode = null;
            }
            $validPost->IP          = $_SERVER['REMOTE_ADDR'];
            $validPost->email       = !empty($post['email']) ? htmlspecialchars(substr($post['email'], 0, 60)) : null;
            $validPost->subject     = !empty($post['subject']) ? htmlspecialchars(substr($post['subject'], 0, 60)) : null;
            $validPost->message     = !empty($post['message']) ? htmlspecialchars(substr($post['message'], 0, MSG_MAX_LENGTH)) : null;
            $validPost->message     = preg_replace('/\r/', '<br>', $validPost->message);
            preg_match_all('/(&gt;&gt;)([0-9]+)/', $validPost->message, $quotes);
            if($quotes){
                $validPost->quotes   = $quotes[2];
            }
            $validPost->message     = PostController::formatMessage($validPost->message, $validPost->parent);
            if($image){
                $validPost->upfile_name = htmlspecialchars($image->upfile_name);
                $validPost->md5         = $image->md5;
                $validPost->file        = $image->filename.'.'.$image->ext;
                $validPost->thumbnail   = $image->thumbnail;
            }else{
                $validPost->upfile_name = null;
                $validPost->md5         = null;
                $validPost->file        = null;
                $validPost->thumbnail   = null;
            }
            $validPost->replies     = null;
            $validPost->password    = crypt($post['password'], PASSWORD_SALT);
                        
            return $validPost;
        }
        /**
         * @param int $id
         * @param string $message
         * @return void
         */
        static function bindQuotes(array $quotes, int $id, int $parent)
        {
            $post = new Post();
            $quotes = array_unique($quotes);
            foreach($quotes as $quote){
                $savedQuotes = $post->findOneByID($quote);
                if($savedQuotes['parent'] == $parent || $savedQuotes['id'] == $parent){
                    $savedQuotes['replies'] .= '<span class="backlink"><a href="'.($parent > 0 ? ROOT.RES_FOLDER.$parent.'#'.$id : ROOT.RES_FOLDER.$id.'#'.$id).'">>>'.$id.'</a></span> ';
                    $post->update('replies', $savedQuotes['replies'], 'id', $quote);
                }
            }
        }
        /**
         * @param string $message
         * @param integer $parent
         * @return string
         */
        static function formatMessage(string $message, string $parent): string
        {
            if(VIDEO && preg_match('/\s*[a-zA-Z\/\/:\.]*youtu(be.com\/watch\?v=|.be\/)([a-zA-Z0-9\-_]+)([a-zA-Z0-9\/\*\-\_\?\&\;\%\=\.]*)/i', $message)){
                $message = preg_replace('/\s*[a-zA-Z\/\/:\.]*youtu(be.com\/watch\?v=|.be\/)([a-zA-Z0-9\-_]+)([a-zA-Z0-9\/\*\-\_\?\&\;\%\=\.]*)/i', '<iframe width="336" height="189" src="https://www.youtube.com/embed/$2" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>', $message, 1);
            }else{
                $message = preg_replace("~[[:alpha:]]+://[^<>[:space:]]+[[:alnum:]/]~", "<a href=\"\\0\" target=\"_blank\" style=\"text-decoration:underline;\">\\0</a>", $message);
            }
            $message = preg_replace('/(&gt;&gt;)([0-9]+)/', '<a class="replyLink" href="'.ROOT.RES_FOLDER.$parent.'#${2}">>>${2}</a>', $message);
            $message = preg_replace('/^(&gt;[^\>](.*))/m', '<span class="quote">${1}</span>', $message);
            return $message;
        }
    }
    class AdminController
    {
        /**
         * @return void
         */
        static function adminPanel()
        {
            $board = new BoardController();
            $post = new Post();
            $page = new Page();
            $user = new User();
            if($admin = $user->connect()){
                if(isset($_GET['res'])){
                    $OP = $post->findOneByID($_GET['res']);
                        $page
                        ->head(ROOT.' - '.$OP['id'])
                        ->title($admin)
                        ->navLinks(null, $OP['id'], 0)
                        ->OP($OP, null, false)
                        ->replies($OP['id'], $admin, false)
                        ->navLinks(null, $OP['id'], 1)
                        ->postForm($OP['id'])
                        ->footer()
                        ->render();
                }elseif(isset($_GET['delreport'])){
                    $report = new Report();
                    $report->delete(['id' => $_GET['delreport']]);
                    header('Location: '.ROOT.'akane.php?admin&reportlist');
                }elseif(isset($_GET['admindel'])){
                    PostController::deletePost($_GET['admindel']);
                    $board->update();
                    header('Location: '.ROOT.'akane.php?admin');
                }elseif(isset($_GET['lock'])){
                    $post->update('locked', 1, 'id', $_GET['lock']);
                    BoardController::updateThread($_GET['lock']);
                    BoardController::update();
                    header('Location: '.ROOT.'akane.php?admin');
                }elseif(isset($_GET['unlock'])){
                    $post->update('locked', 0, 'id', $_GET['unlock']);
                    BoardController::updateThread($_GET['unlock']);
                    BoardController::update();
                    header('Location: '.ROOT.'akane.php?admin');
                }elseif(isset($_GET['stick'])){
                    $post->update('sticky', 1, 'id', $_GET['stick']);
                    BoardController::updateThread($_GET['stick']);
                    BoardController::update();
                    header('Location: '.ROOT.'akane.php?admin');
                }elseif(isset($_GET['unstick'])){
                    $post->update('sticky', 0, 'id', $_GET['unstick']);
                    BoardController::updateThread($_GET['unstick']);
                    BoardController::update();
                    header('Location: '.ROOT.'akane.php?admin');
                }elseif(isset($_GET['ban'])){
                    if(isset($_POST['IP'])){
                        $ban = new Ban();
                        $ban->IP = $_POST['IP'];
                        $ban->reason = $_POST['reason'];
                        $ban->expires = time() + intval($_POST['expires']);
                        $ban->create();
                        $ban->insert();
                        $post = new Post();
                        if($_POST['publicban']){
                            $banned = $post->findOne('id', $_POST['id']);
                            $post->update('message', $banned['message'].'<br><span style="font-size:24pt;font-weight:bold;color:red;">CET UTILISATEUR À ÉTÉ BANNI POUR CE MESSAGE</span>', 'id', $_POST['id']);
                            BoardController::updateThread($_POST['id']);
                        }else{
                            PostController::deletePost($_POST['id']);
                        }
                        BoardController::update();
                        header('Location: '.ROOT.'akane.php?admin&banlist');
                    }else{
                        $page
                            ->head('Registrer un ban')
                            ->title($admin)
                            ->banForm($_GET['ban'])
                            ->footer()
                            ->render();
                    }
                }elseif(isset($_GET['liftban'])){
                    $ban = new Ban();
                    $ban->delete(['id' => $_GET['liftban']]);
                    header('Location: '.ROOT.'akane.php?admin&banlist');
                }elseif(isset($_GET['banlist'])){
                    $page
                    ->head('Liste des bannis')
                    ->title($admin)
                    ->banList()
                    ->footer()
                    ->render();
                }elseif(isset($_GET['reportlist'])){
                    $page
                    ->head('Signalements')
                    ->title($admin)
                    ->reportList()
                    ->footer()
                    ->render();
                }elseif(isset($_GET['rebuildall'])){
                    header('Location: '.ROOT.'akane.php?admin');
                    BoardController::rebuildAll();
                }elseif(isset($_GET['logout'])){
                    session_unset();
                    session_destroy();
                    header('Location: '.ROOT);
                }else{
                    $board->update($admin, isset($_GET['page']));
                }
            }else{
                $page->head('Authentification')->auth()->render();
            }
        }
    }
//----------Main-----------//
    //Paramétrage de PHP
    setlocale(LC_TIME,'fr_FR');
    date_default_timezone_set('Europe/Paris');
    error_reporting(E_ALL);
    session_start();
    setcookie(session_name(), session_id(), time() + 2592000);
    $board = new Board();
    $board->init(); //Si le forum est neuf, création des bases de données

    if(isset($_POST['newpost'])){ //Vérifie si c'est un nouveau message
        PostController::processPost();
    }elseif(isset($_GET['admin'])){
        AdminController::adminPanel();
    }elseif(isset($_GET['del']) && isset($_GET['deletepost'])){
        PostController::userDelete();
    }elseif(isset($_GET['del']) && isset($_GET['report'])){
        PostController::userReport();
    }else{
        header('Location: '.ROOT);
    }
