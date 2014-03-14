<?php

/* WEB-APP : WebMCR (С) 2013-2014 NC22 
   MODULE : SkinPoser (C) 2013-2014 NC22 */

if (!defined('MCR'))
    exit;

define('MCR_SP_VER', '1.2');

require_once(MCR_ROOT . 'instruments/skin.class.php');

Class SPItem extends Item
{
    const BASE_NAME = 'sp_nc';
    
    private $base_dir;
    private $base_url;
    
    private $comments;
    private $comment_last;
    private $db_likes;
    private $db_bad_skins;
    private $db_ratio;
    private $name;
    private $fname;
    private $fsize;
    private $dislikes;
    private $likes;
    private $ratio;
    private $gender;
    private $downloads;
    private $user_id;
    
    public function __construct($id = false, $style_sd = false)
    {
        global $bd_names, $site_ways;

        parent::__construct($id, ItemType::Skin, $bd_names['sp_skins'], $style_sd); 
        
        $this->base_dir = MCR_ROOT . $site_ways['sp_dir'] . 'skins/';
        $this->base_url = $site_ways['sp_dir'] . 'skins/';
        $this->db_likes = $bd_names['likes'];
        $this->db_bad_skins = $bd_names['sp_bad_skins'];
        $this->db_ratio = $bd_names['sp_skins_ratio'];
        
        if (!$this->id) return;
        
        $line = getDB()->fetchRow("SELECT * FROM `{$this->db}` WHERE `id`='" . $this->id . "'");
        if (!$line) return;     
 
        $this->name = $line['name'];
        $this->fname = $line['fname'];
        $this->fsize = $line['fsize'];
        $this->dislikes = (int) $line['dislikes'];
        $this->likes = (int) $line['likes'];
        $this->ratio = (int) $line['ratio']; // @todo rename to scale
        $this->gender = (int) $line['gender'];
        $this->downloads = (int) $line['downloads'];
        $this->comments = (int) $line['comments'];
        $this->user_id = (int) $line['user_id'];
        $this->comment_last = ( $line['comment_last'] === '0000-00-00 00:00:00' ) ? false : $line['comment_last'];
    }

    public function OnComment()
    {
        if (!$this->Exist())
            return false;

        $this->comment_last = date("Y-m-d H:i:s");
        $this->comments++;

        getDB()->ask("UPDATE `{$this->db}` SET `comments` = '" . $this->comments . "', `comment_last` = '" . $this->comment_last . "' WHERE `id`='" . $this->id . "'");
    }

    public function OnDeleteComment()
    {

        if (!$this->Exist())
            return false;
        $this->comments--;

        getDB()->ask("UPDATE `{$this->db}` SET `comments` = '" . $this->comments . "' WHERE `id`='" . $this->id . "'");
    }

    public function Create($post_name, $gender = 2, $max_size = 20, $max_scale = 1, $del_blist = false, $method = 'post')
    {
        global $user;

        $max_size = (int) $max_size;
        $max_scale = (int) $max_scale;
        $gender = (int) $gender;

        if ($gender > 2 or $gender < 0)
            $gender = 2;

        if (!empty($user) and $user->Exist())
            $user_id = $user->id();
        else
            $user_id = 0;

        if ($method == 'post') {

            if (!POSTGood($post_name))
                return 1;
            $new_file_info = POSTSafeMove($post_name, $this->base_dir);
            if (!$new_file_info)
                return 2;

            $size_mb = $new_file_info['size_mb'];
            $way = $this->base_dir . $new_file_info['tmp_name'];
        } else {

            if (!file_exists($post_name))
                return 1;

            $size_mb = round(filesize($post_name) / 1024 / 1024, 2);
            $way = $post_name;
        }

        $hash = md5_file($way);

        if ($del_blist)
            getDB()->ask("DELETE FROM `{$this->db_bad_skins}` WHERE hash='$hash'");

        $line = getDB()->fetchRow("SELECT `id`, 'good_skin' AS `type` FROM `{$this->db}` "
                                . "WHERE hash='$hash' UNION SELECT `id`, 'bad_skin' "
                                . "AS `type` FROM `{$this->db_bad_skins}` WHERE `hash`='$hash'");
                                
        if ($line) {

            unlink($way);
            if ($line['type'] == 'bad_skin')
                return 3;
            else
                return $line['id'] * -1;
        }

        if ($max_size < $size_mb * 1024) {

            unlink($way);
            return 4;
        }
        
        $new_file_scale = SkinViewer2D::isValidSkin($way);
        
        if (!$new_file_scale or $new_file_scale['scale'] > $max_scale) {

            unlink($way);
            return 5;
        }

        $new_file_scale = (int) $new_file_scale['scale'];
        
        getDB()->ask("INSERT INTO `{$this->db}` (hash, fsize, ratio, gender, user_id) VALUES ('" . $hash . "','" . $size_mb . "','" . $new_file_scale . "', '" . $gender . "', '" . $user_id . "')");

        $this->id = getDB()->lastInsertId();
        $new_name = 'sp_nc' . $this->id . '.png';
        $new_way = $this->base_dir . $new_name;

        getDB()->ask("UPDATE `{$this->db}` SET `fname` = '" . $new_name . "' WHERE `id`='" . $this->id . "'");

        if (file_exists($new_way))
            unlink($new_way);

        if (rename($way, $new_way))
            chmod($new_way, 0777);
        else {
            unlink($way);
            getDB()->ask("DELETE FROM `{$this->db}` WHERE `id`='" . $this->id . "'");
            return 6;
        }

        $preview = SkinViewer2D::savePreview($this->base_dir . 'preview/' . $new_name, $new_way, false, false, 160);
        if (!$preview) {
            unlink($new_way);
            getDB()->ask("DELETE FROM `{$this->db}` WHERE `id`='" . $this->id . "'");
            return 7;
        } else
            imagedestroy($preview);

        getDB()->ask("LOCK TABLES `{$this->db_ratio}` WRITE;");
        getDB()->ask("INSERT INTO `{$this->db_ratio}` (ratio) VALUES ('$new_file_scale') "
                   . "ON DUPLICATE KEY UPDATE `num`= num + 1;");
        getDB()->ask("UNLOCK TABLES;");

        $this->name = '';
        $this->fname = $new_name;
        $this->fsize = $size_mb;
        $this->dislikes = 0;
        $this->likes = 0;
        $this->ratio = $new_file_scale;
        $this->gender = $gender;
        $this->downloads = 0;
        $this->user_id = $user_id;

        return 0;
    }

    public function Rebuild()
    {
        $skin_way = $this->base_dir . $this->fname;
        $preview_way = $this->base_dir . 'preview/' . $this->fname;

        if (!file_exists($skin_way)) {

            $this->Delete();
            vtxtlog('[Rebuild][SkinViewer2D] SPItem ID ' . $this->id . ' not founded - delete');

            return false;
        }

        if (file_exists($preview_way))
            unlink($preview_way);

        $skin_scale = SkinViewer2D::isValidSkin($skin_way);
        if (!$skin_scale) {
            $this->Delete();
            vtxtlog('[Rebuild][SkinViewer2D] SPItem ID ' . $this->id . ' wrong skin format - delete');

            return false;
        }

        if (!SkinViewer2D::savePreview($preview_way, $skin_way, false, false, 160)) {

            $this->Delete();
            vtxtlog('[Rebuild][SkinViewer2D] Fail to create preview for SPItem ID ' . $this->id);

            return false;
        }

        if (!file_exists($preview_way))
            vtxtlog('[Rebuild][SkinViewer2D] Fail to save preview for SPItem ID ' . $this->id);
        
        $skin_scale = (int) $skin_scale['scale'];
        
        getDB()->ask("LOCK TABLES `{$this->db_ratio}` WRITE;");
        getDB()->ask("INSERT INTO `{$this->db_ratio}` (ratio) VALUES ('" . $skin_scale . "') ON DUPLICATE KEY UPDATE `num`= num + 1;");
        getDB()->ask("UNLOCK TABLES;");

        if ($this->ratio != $skin_scale) {
            getDB()->ask("UPDATE `{$this->db}` SET `ratio` = '" . $skin_scale . "' WHERE `id`='" . $this->id . "'");
            $this->ratio = $skin_scale;
        }
    }

    public function ApplayToUser($user_id)
    {
        if (!$this->Exist())
            return false;

        $work_user = new User($user_id);
        if (!$work_user->id())
            return false;

        $female = $work_user->isFemale();
        if (($this->isFemaleSkin() and !$female) or (!$this->isFemaleSkin() and $female))
            return false;

        if (!$work_user->getPermission('sp_change') or $work_user->getPermission('max_ratio') < $this->ratio)
            return false;

        $work_user->deleteSkin();
        $work_user->deleteBuffer();

        $user_skin_way = $work_user->getSkinFName();

        if (copy($this->base_dir . $this->fname, $user_skin_way))
            chmod($user_skin_way, 0777);
        else
            return false;

        if (!strcmp($work_user->defaultSkinMD5(), md5_file($work_user->getSkinFName())))
            $work_user->defaultSkinTrigger(true);
        else
            $work_user->defaultSkinTrigger(false);

        getDB()->ask("UPDATE `{$this->db}` SET `downloads` = downloads + 1 WHERE `id`='" . $this->id . "'");
        return true;
    }

    public function isFemaleSkin()
    {
        if (!$this->Exist())
            return false;

        if ($this->gender == 1)
            return true;
        return false;
    }

    public function Like($dislike = false)
    {
        global $user;

        if (!$this->Exist() or empty($user) or !$user->lvl())
            return 0;

        $like = new ItemLike(ItemType::Skin, $this->id, $user->id());

        return $like->Like($dislike);
    }

    public function Download()
    {
        if (!$this->Exist() or empty($user) or !$user->getPermission('sp_download'))
            return false;        
        
        header('Content-Type: image/png');
        header('Cache-Control:no-cache, must-revalidate');
        header('Expires:0');
        header('Pragma:no-cache');
        header('Content-Length:' . filesize($this->base_dir . $this->fname));
        header('Content-Disposition: attachment; filename="' . $this->fname . '"');
        header('Content-Transfer-Encoding:binary');

        getDB()->ask("UPDATE `{$this->db}` SET `downloads` = downloads + 1 WHERE `id`='" . $this->id . "'");

        readfile($this->base_dir . $this->fname);
        return true;
    }

    public function getInfo()
    {
        if (!$this->Exist())
            return false;

        return array('id' => $this->id,
            'name' => $this->fname,
            'size' => $this->fsize,
            'dislikes' => $this->dislikes,
            'likes' => $this->likes,
            'downloads' => $this->downloads,
            'gender' => $this->gender,
            'ratio' => $this->ratio);
    }

    public function Show($show_comments = true, $full_info = false)
    {
        global $config, $user;

        if (!$this->Exist())
            return '';

        $available = true;
        $admin = false;

        if (!empty($user)) {

            $female = $user->isFemale();

            if ($user->group() == 3)
                $admin = true;

            if (($this->isFemaleSkin() and !$female) or (!$this->isFemaleSkin() and $female))
                $available = false;

            if (!$user->getPermission('sp_change') or $user->getPermission('max_ratio') < $this->ratio)
                $available = false;
        } else
            $available = false;

        if ($show_comments) {

            $skin_comments = $this->comments;
            $skin_comment_today = ($this->comment_last and date('Ymd') == date('Ymd', strtotime($this->comment_last)) ) ? true : false;
        }

        $skin_info = $this->getInfo();

        $skin_id = $skin_info['id'];
        $skin_likes = $skin_info['likes'];
        $skin_dislikes = $skin_info['dislikes'];
        $skin_name = $skin_info['name'];
        $skin_size = $skin_info['size'];

        if ($skin_size <= 0)
            $skin_size = '< 0.01';

        $skin_ratio = (64 * $skin_info['ratio']) . 'x' . (32 * $skin_info['ratio']);
        $skin_gender = $skin_info['gender'];
        $skin_downloads = $skin_info['downloads'];
        $skin_preview = $this->base_url . 'preview/' . self::BASE_NAME . $skin_id . '.png';

        ob_start();
        include $this->GetView('skin.html');

        if ($full_info) {

            $skin_download = $config['sp_download'];
            if (!$skin_download and (empty($user) or !$user->getPermission('sp_download'))) {
                $skin_download = false;  
            }
            include $this->GetView('skin_info.html');
        }

        return ob_get_clean();
    }

    public function SetGender($new_gender)
    {
        $new_gender = (int) ($new_gender);
        if ($new_gender < 0 or $new_gender > 2)
            $new_gender = 0;

        getDB()->ask("UPDATE `{$this->db}` SET `gender` = '$new_gender' WHERE `id`='" . $this->id . "'");

        $this->gender = $new_gender;
    }

    public function Delete()
    {
        global $bd_names;

        if (!$this->Exist())
            return false;

        $skin_way = $this->base_dir . $this->fname;
        $preview_way = $this->base_dir . 'preview/' . $this->fname;

        $line = getDB()->fetchRow("SELECT `hash` FROM `{$this->db}` WHERE `id`='" . $this->id . "'");

        if ($line) {
            getDB()->ask("INSERT INTO {$this->db_bad_skins} (hash) VALUES ('" . $line['hash'] . "')");
        }

        if (file_exists($skin_way))
            unlink($skin_way);
        if (file_exists($preview_way))
            unlink($preview_way);

        getDB()->ask("DELETE FROM `{$this->db_likes}` WHERE `item_id`='" . $this->id . "' AND `item_type`='" . ItemType::Skin . "'");
        getDB()->ask("LOCK TABLES `{$this->db_ratio}` WRITE;");
        getDB()->ask("UPDATE `{$this->db_ratio}` SET `num` = num - 1 WHERE `ratio`='" . $this->ratio . "'");
        getDB()->ask("UNLOCK TABLES;");

        return parent::Delete();
    }

}

Class SkinManager extends View
{
    private static $permissions = array(
        "sp_change" => 'bool',
        "sp_upload" => 'bool',
        "sp_download" => 'bool',
    );
    
    private $base_url;
    private $url_params;
    private $db;
    private $db_ratio;
    private $discus;
    private $download;
    private $answer;

    /**
     * @deprecated since v2.4b 
     */
    private $st_subdir = null;
    
    public function SkinManager($style_sd = false, $base_url = 'index.php?mode=skinposer', $url_params = false)
    {
        global $bd_names, $config;

        parent::View($style_sd);
        
        if (!$this->st_subdir) $this->st_subdir = $style_sd;
        
        if (isset($bd_names['sp_skins'])) {

            $this->db = $bd_names['sp_skins'];
            $this->db_ratio = $bd_names['sp_skins_ratio'];

            $this->download = $config['sp_download'];
            $this->discus = $config['sp_comments'];
        } else
            $this->db = false;

        $this->type = ItemType::Skin;
        $this->db_likes = $bd_names['likes'];
        $this->base_url = $base_url;
        $this->url_params = (!$url_params) ? '' : $url_params;
        $this->answer = '';

        Group::$permissions = array_merge(Group::$permissions, self::$permissions);
    }

    public function FindNewSkins($tag)
    {
        global $site_ways;
        
        if ($tag < 0 or $tag > 2) $tag = 2;
        
        $skin_dir_way = MCR_ROOT . $site_ways['sp_dir'] . 'upload/';

        if (!is_dir($skin_dir_way)) {
            $this->answer .= 'Папка загрузки новых скинов не существует  <br />';
            return false;
        }

        $skin_dir = opendir($skin_dir_way);
        @ini_set("max_execution_time", 0);

        $skin_black = 0;
        $skin_exist = 0;
        $skin_add = 0;
        $skin_error = 0;
        $start_time = microtime(true);
        $flush_trg = false;

        while ($filename = readdir($skin_dir)) {

            unset($new_skin);
            unset($result);

            if (microtime(true) - $start_time > 2) {

                if (!$flush_trg) {
                    echo ' Loading...';
                    $flush_trg = true;
                }

                echo '.';
                flush();
                $start_time = microtime(true);
            }

            if ($filename == '.' or $filename == '..' or $filename == '.htaccess')
                continue;

            $new_skin = new SPItem();
            $result = $new_skin->Create($skin_dir_way . $filename, $tag, 5000, 24, false, 'file');

            if ($result == 3)
                $skin_black++;
            elseif ($result < 0)
                $skin_exist++;
            elseif ($result == 0)
                $skin_add++;
            else {
                //$this->answer .= $result . '['.$skin_dir . $filename.']' .'<br />';
                $skin_error++;
            }
        }
        
        closedir($skin_dir);
        $this->answer .= 'Добавление файлов завершено. <br /> Добавлены: ' . $skin_add . ' <br /> Проигнорированы: <br />  Черный список: ' . $skin_black . ' Дубликаты: ' . $skin_exist . ' Ошибки: ' . $skin_error;
    }

    public function RebuildAll()
    {
        getDB()->ask("LOCK TABLES `{$this->db_ratio}` WRITE;");
        getDB()->ask("DELETE FROM `{$this->db_ratio}` WHERE `ratio` != '0'");
        getDB()->ask("UNLOCK TABLES;");

        @ini_set("max_execution_time", 0);

        $result = getDB()->ask("SELECT `id` FROM `" . $this->db . "`");
        
            while ($line = $result->fetch('num')) {
                $skin = new SPItem($line[0]);
                $skin->Rebuild();
            }

        $this->answer .= 'Обновление существующей базы выполнено <br />';
    }
    
    public function TryAutoConfigure()
    {
        global $config, $bd_names, $site_ways, $menu;

        if (!isset($menu))
            $menu = new Menu();

        if ($menu->IsItemExists('skinposer') === false or $menu->IsItemExists('sp_admin') === false) {

            $tool_sp_btn = array(
                'name' => 'Образы',
                'url' => ($config['rewrite']) ? 'go/skinposer' : '?mode=skinposer',
                'parent_id' => -1,
                'lvl' => 1,
                'permission' => -1,
                'config' => 'sp_online'
            );

            $adm_sp_btn = array(
                'name' => 'SkinPoser',
                'url' => '?mode=skinposer&do=admin',
                'parent_id' => 'admin',
                'lvl' => 15,
                'permission' => -1
            );

            if (!$menu->SaveItem('skinposer', 'left', $tool_sp_btn) or
                    !$menu->SaveItem('sp_admin', 'right', $adm_sp_btn))
                $this->answer .= 'Не удалось добавить пункт меню <br />';
        }

        if ($this->db)
            return false;

        require(MCR_ROOT . 'instruments/sp2/install/config.php');
        require(MCR_ROOT . 'instruments/sp2/install/sql.php');

        $this->db = $bd_names['sp_skins'];
        $this->db_ratio = $bd_names['sp_skins_ratio'];
        $this->discus = $config['sp_comments'];

        loadTool('alist.class.php');
        if (!MainConfig::SaveOptions())
            $this->answer .= 'Ошибка применения настроек <br />';

        if ($this->answer)
            return $this->ShowAdminForm();

        return false;
    }

    public function ShowAdminForm()
    {
        global $bd_names, $config;

        $info = $this->answer;
        $configUpd = false;
        
        if (isset($_POST['sp_group_edit'])) {

            $group = new Group(Filter::input('group', 'post', 'int'));
            $permissions = $group->GetAllPermissions();

            foreach (self::$permissions as $key => $value) {
                if ($value == 'bool')
                    $permissions[$key] = (Filter::input($key, 'post', 'int')) ? 1 : 0;
                elseif (isset($_POST[$key]))
                    $permissions[$key] = Filter::input($key, 'post', 'int');
                else
                    continue;
            }

            $group->Edit($group->GetName(), $permissions);
            
        } elseif (isset($_POST['sp_config_set'])) {

            // @todo move rebuild action some where else
            
            $rebuild_items = Filter::input('rebuild_items', 'post', 'bool');
            
            if ($rebuild_items)
                $this->RebuildAll();

            $config['sp_online'] = !Filter::input('sp_offline', 'post', 'bool');
            $config['sp_upload'] = Filter::input('sp_upload', 'post', 'bool');
            $config['sp_download'] = Filter::input('sp_download', 'post', 'bool');
            $config['sp_comments'] = Filter::input('sp_comments', 'post', 'bool');
            $configUpd = true;
            
        } elseif (isset($_POST['sp_tables_set'])) { 

            $bd_skins = Filter::input('bd_skins', 'post');
            $bd_bad_skins = Filter::input('bd_bad_skins', 'post');
            $bd_skins_ratio = Filter::input('bd_skins_ratio', 'post');  
       
            if ($bd_skins)
                if (!getDB()->isColumnExist($bd_skins, 'fname'))
                    $this->answer .= 'Таблица не найдена ( ' . $bd_skins . ' )  <br />';
                else
                    $bd_names['sp_skins'] = $bd_skins;

            if ($bd_bad_skins)
                if (!getDB()->isColumnExist($bd_bad_skins, 'hash'))
                    $this->answer .= 'Таблица не найдена ( ' . $bd_bad_skins . ' )  <br />';
                else
                    $bd_names['sp_bad_skins'] = $bd_bad_skins;

            if ($bd_skins_ratio)
                if (!getDB()->isColumnExist($bd_skins_ratio, 'num'))
                    $this->answer .= 'Таблица не найдена ( ' . $bd_skins_ratio . ' )  <br />';
                else
                    $bd_names['sp_skins_ratio'] = $bd_skins_ratio;

            if ($bd_skins or $bd_bad_skins or $bd_skins_ratio)            
             $configUpd = true;
            
        } elseif (isset($_POST['sp_upload_set'])) {
            
            $find_items = Filter::input('find_items', 'post', 'bool');
            $tag = Filter::input('rebuild_type', 'post', 'int'); 
            
            if ($find_items)
                $this->FindNewSkins($tag);            
        }

        if ($configUpd) {
            loadTool('alist.class.php');
            
            if (!MainConfig::SaveOptions())
               $this->answer .= 'Ошибка применения настроек <br />';
            else
                $this->answer .= 'Настройки изменены <br />';
        }
        $info = $this->answer;
        $result = getDB()->ask("SELECT `id`, `name` FROM `{$bd_names['groups']}` ORDER BY `name` DESC LIMIT 0,90");

        ob_start();

        while ($line = $result->fetch()) {

            $group_i = new Group($line['id']);
            $group = $group_i->GetAllPermissions();
            $group['name'] = $line['name'];
            $group['id'] = $line['id'];

            include $this->GetView('admin/group.html');
        }
        $groups = ob_get_clean();

        ob_start();
        include $this->GetView('admin/constants.html');

        return ob_get_clean();
    }

    public function ShowSortTypeSelector($order_by = 'id', $sort = 1)
    {
        ob_start();
        include $this->GetView('sort.html');

        return ob_get_clean();
    }

    public function ShowSPStateInfo()
    {
        global $config;

        $html = '';

        if (!$config['sp_online'])
            $html .= $this->ShowPage('admin/sp_closed_warn.html');

        if (!$config['sp_upload'])
            $html .= $this->ShowPage('admin/sp_upload_warn.html');

        return $html;
    }

    public function ShowGenderSelector($current = 4)
    {
        $html = '<option value="0" ' . (($current == 0) ? 'selected' : '') . '>Без тега + Мужские</option>';
        $html .= '<option value="1" ' . (($current == 1) ? 'selected' : '') . '>Без тега + Женские</option>';
        $html .= '<option value="2" ' . (($current == 2) ? 'selected' : '') . '>Без тега</option>';
        $html .= '<option value="3" ' . (($current == 3) ? 'selected' : '') . '>Мужские</option>';
        $html .= '<option value="4" ' . (($current == 4) ? 'selected' : '') . '>Женские</option>';

        return $html;
    }

    public function ShowSideMenu($gender = 0, $ratio = 1)
    {
        global $user;

        $gender_list = $this->ShowGenderSelector($gender);
        $ratio_list = '';
        $admin = false;

        if (!empty($user) and $user->group() == 3)
            $admin = true;

        $result = getDB()->ask("SELECT `ratio`,`num` FROM `" . $this->db_ratio . "` ORDER BY `ratio` LIMIT 0, 90");

        while ($line = $result->fetch('num')) {
            $ratio_list .= '<option value="' . $line[0] . '" ' . (($line[0] == $ratio) ? 'selected' : '')
                    . '>' . (64 * $line[0]) . 'x' . (32 * $line[0]) . ' (' . $line[1] . ')</option>';
        }

        ob_start(); include $this->GetView('ratio.html');
        return ob_get_clean();
    }

    public function ShowAddForm()
    {
        global $user;

        if (empty($user))
            exit;

        $max_ratio = $user->getPermission('max_ratio');
        $max_ratio_text = (64 * $max_ratio) . 'x' . (32 * $max_ratio);

        $max_fsize = $user->getPermission('max_fsize');

        ob_start(); include $this->GetView('add_skin_form.html');
        return ob_get_clean();
    }

    public function ShowById($id, $list = 1)
    {
        $mode_txt = 'Отдельный образ';
        $gender_txt = lng('NOT_SET');
        $skins = '';

        $skins_count = 1;
        $skin = new SPItem($id, $this->st_subdir);

        if (!$skin->Exist())
            $html_skin_list = 'Скин удален';
        else {

            $skins = $skin->Show(false, $full_info = true);

            ob_start();
            include $this->GetView('skin_container.html');

            $html_skin_list = ob_get_clean();

            if ($this->discus) {

                loadTool('comment.class.php');

                $comments = new CommentList($skin, $this->base_url . '&cid=' . $id, 'news/comments/');
                $html_skin_list .= $comments->Show($list);
                $html_skin_list .= $comments->ShowAddForm();
            }
        }

        ob_start(); include $this->GetView('main.html');
        return ob_get_clean();
    }

    public function ShowRandom()
    {
        $skins_count = getDB()->fetchRow("SELECT COUNT(*) FROM {$this->db}", false, 'num');
        if (!$skins_count) {
            return $this->ShowById(0);            
        } else $skins_count = (int) $skins_count[0];
        
        $line = getDB()->fetchRow("SELECT `id` FROM {$this->db} LIMIT " . rand(0, $skins_count - 1) . ", 1", false, 'num');
        if (!$line) return false;
        
        return $this->ShowById((int) $line[0]);
    }

    public function ShowSkinList($list = 1, $per_page = 20, $gender = 2, $order_by = false, $sort = 1, $mode = 'base', $ratio = 1)
    {
        global $user;

        $list = (int) $list;
        if ($list <= 0)
            $list = 1;
        $ratio = (int) $ratio;
        if (($ratio < 1 or $ratio > 64) and $ratio != 1337 and $ratio != 31337)
            return '';

        switch ($order_by) {
            case 'id': $order_by = 'id';
                break;
            case 'likes': $order_by = 'likes';
                break;
            case 'comment_last': $order_by = 'comment_last';
                break;
            case 'comments': $order_by = 'comments';
                break;
            default :
                if ($this->discus)
                    $order_by = 'comment_last';
                else
                    $order_by = 'id';
                break;
        }

        $sort = ($sort == 2) ? 'DESC' : '';

        // echo '['.$list.' |'.$per_page.' |'.$gender.' |'.$order_by.' |'.$sort.' |'.$mode.' |'.$ratio.']';

        $mode_txt = 'Классические образы (64х32)';
        $gender_txt = 'Женские';

        if ($gender < 2) {

            $gender_txt = (($gender) ? 'Женские' : 'Мужские') . ' и без тега';
            $base_sql = "WHERE (`gender` = '2' OR `gender` = '" . (($gender) ? 1 : 0) . "')";
        } elseif ($gender == 2) {

            $gender_txt = 'Без тега';
            $base_sql = "WHERE `gender` = '2'";
        } elseif ($gender == 3) {

            $gender_txt = 'Мужские';
            $base_sql = "WHERE `gender` = '0'";
        } else
            $base_sql = "WHERE `gender` = '1'";

        if ($mode == 'HD')
            $mode_txt = 'Высокое разрешение';
        elseif ($mode == 'likes') {

            $gender_txt = 'Любые';
            $mode_txt = 'Понравившиеся';
        }

        if ($mode != 'likes') {

            if ($mode == 'HD' and $ratio == 1337)
                $base_sql .= " AND `ratio` != '1'";
            else
                $base_sql .= " AND `ratio` = '" . $ratio . "'";

            $skins_count = getDB()->fetchRow("SELECT COUNT(*) FROM `" . $this->db . "` " . $base_sql, false, 'num');
        } else {

            if (empty($user))
                $skins_count = 0;
            else
                $skins_count = getDB()->fetchRow("SELECT COUNT(*) FROM `" . $this->db_likes . "` WHERE "
                                          . "`user_id`= '" . $user->id() . "' AND `item_type` = '" . $this->type . "' "
                                          . "AND `var`='1'", false, 'num');
        }

        if (!$skins_count) $skins_count = 0;
        else $skins_count = (int) $skins_count[0];
        
        $html_skin_list = '';
        if ($list > ceil($skins_count / $per_page))
            $list = 1;

        if (!$skins_count)
            $html_skin_list = $this->ShowPage('skin_empty.html');
        else {

            if ($mode != 'likes') {
                $result = getDB()->ask("SELECT `id` FROM `" . $this->db . "` " . $base_sql . " "
                                     . "ORDER BY `$order_by` $sort LIMIT " . ($per_page * ($list - 1)) . ", $per_page");
            } else {
                $sql = "SELECT `item_id` AS 'id' FROM `{$this->db}` "
                      . "LEFT JOIN `{$this->db_likes}` ON "
                      . $this->db . ".id = {$this->db_likes}.item_id "
                      . "WHERE {$this->db_likes}.user_id = '" . $user->id() . "' AND "
                      . "{$this->db_likes}.item_type = '{$this->type}' AND "
                      . "{$this->db_likes}.var = '1' ORDER BY {$this->db_likes}.id "
                      . "DESC LIMIT " . ($per_page * ($list - 1)) . ", $per_page";

                $result = getDB()->ask($sql);
            }
            
            while ($line = $result->fetch('num')) {
                $skin = new SPItem($line[0], $this->st_subdir);
                $html_skin_list .= $skin->Show($this->discus);
            }
            
            if (!$html_skin_list) $this->ShowPage('skin_empty.html');
        }

        $html_skin_list .= $this->arrowsGenerator($this->base_url . $this->url_params . '&', $list, $skins_count, $per_page);

        ob_start(); include $this->GetView('main.html');
        return ob_get_clean();
    }
}
