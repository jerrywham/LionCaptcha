<?php 
/**
* Plugin LionCaptcha
*
* @package  PLX
* @version  1.0
* @date 12/08/17
* @author Cyril MAGUIRE
**/
if(!defined("PLX_ROOT")) exit; ?>
<?php 
define('LC_COMMENT', '# File for turing test questions. Structure of the file is very simple, first
# line of a record is "--" which indicates new record (question). Second line
# is question and third line is right answer. You can add more answers to third
# separated by comma. Everything else is ignored, so you can use it as comments.
# In that case, please use something like "#" or "//" to make it clear it
# is comment. Comparing answers is case insensitive.

');
function getExt($file)
{
    $composants = explode('.',$file);
    return end($composants);
}
     if(!empty($_POST)) {
        
        if (isset($_POST['newFile']) && !is_file(PLX_ROOT.$plxPlugin->getParam('dir').plxUtils::strCheck($_POST['newFile']).'_questions.txt')) {
            touch(PLX_ROOT.$plxPlugin->getParam('dir').plxUtils::strCheck($_POST['newFile']).'_questions.txt');
        }
        $txt = LC_COMMENT;
        if (isset($_POST['q']) && isset($_POST['r']) && isset($_POST['f']) && count($_POST['q']) == count($_POST['r'])) {
            if (is_file(PLX_ROOT.$plxPlugin->getParam('dir'). plxUtils::strCheck($_POST["f"]))) {
                foreach ($_POST['q'] as $key => $value) {
                    $txt .= "--\n";
                    $txt .= plxUtils::strCheck($_POST['q'][$key])."\n";
                    $txt .= plxUtils::strCheck($_POST['r'][$key])."\n";
                }
                $txt = substr($txt,0,-1);
            }
            file_put_contents(PLX_ROOT.$plxPlugin->getParam('dir'). plxUtils::strCheck($_POST["f"]),$txt);
        }
        if(isset($_POST["genererInput"]) && isset($_POST["dir"])) {
            $plxPlugin->setParam("dir", (empty($_POST["dir"]) ? 'data/LionCaptcha/' : plxUtils::strCheck($_POST["dir"])), "string");
            $plxPlugin->setParam("genererInput", ($_POST["genererInput"] == 'on' ? 1 : 0), "numeric");
            $plxPlugin->saveParams();
        }
        header("Location: parametres_plugin.php?p=LionCaptcha");
        exit;
    }
?>

<p><?php $plxPlugin->lang("L_DESCRIPTION") ?></p>
<div id="base">
<h2><?php $plxPlugin->lang('L_GENERAL_CONFIG') ?></h2>
<form action="parametres_plugin.php?p=LionCaptcha" method="post" >

    <p>
        <label><?php $plxPlugin->lang("L_DIR") ?> : 
            <input type="text" name="dir" value="<?php echo ($plxPlugin->getParam("dir") == '' ? 'data/LionCaptcha/' : $plxPlugin->getParam("dir"));?>" size="20"/>
            <a class="hint"><span><?php echo L_HELP_SLASH_END ?></span></a>&nbsp;<strong>ex: data/LionCaptcha/</strong>
        </label>
    </p>
    <p>
        <label><?php $plxPlugin->lang("L_MAKE_INPUT") ?> : 
            <input type="checkbox" name="genererInput" <?php echo ($plxPlugin->getParam("genererInput") == 1 ? 'checked="checked"' : '');?>/>
        </label>
    </p>
    <br />
    <input type="submit" name="submit" value="<?php $plxPlugin->lang("L_REC") ?>"/>
</form>
</div>
<h2><?php $plxPlugin->lang('L_FILES_CONFIG') ?></h2>
<p><?php $plxPlugin->lang('L_HELP_MAKE_FILE'); ?></p>
<?php if($plxPlugin->getParam("dir") != '' && is_dir(PLX_ROOT.$plxPlugin->getParam('dir'))) {
    $trad = array();
    $ritit = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PLX_ROOT.$plxPlugin->getParam("dir"), RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); 
    $iter = array(); 
    foreach ($ritit as $splFileInfo) { 
       $path = $splFileInfo->isDir() 
             ? array($splFileInfo->getFilename() => array()) 
             : array($splFileInfo->getFilename()); 

       for ($depth = $ritit->getDepth() - 1; $depth >= 0; $depth--) { 
           $path = array($ritit->getSubIterator($depth)->current()->getFilename() => $path); 
       } 
       $iter = array_merge_recursive($iter, $path); 
    } 
    foreach ($iter as $key => $file) {
        if ($file != 'index.html' && getExt($file) == 'txt') {
            $trad[$file] = file(PLX_ROOT.$plxPlugin->getParam("dir").$file);
        }
    }
    foreach ($trad as $name => $lines) {
        $i = 0;
        echo '<div id="trad_'.substr($name,0,-4).'" class="trad-form">
        <h3>'.$plxPlugin->getLang('L_TITLE_MODIF_FILE').' "'.$name.'"</h3>
        <form action="parametres_plugin.php?p=LionCaptcha" method="post" accept-charset="utf-8">
        ';
        foreach ($trad[$name] as $line => $content) {
            if ($line > 7) {
                $content = trim($content);
                if ($content != '--' && $content != '') {
                    if ($i == 0) {
                        echo '<div class="inline">'.$plxPlugin->getLang('L_QUESTION').' : ';
                        plxUtils::printInput('q['.$line.']',$content,'text','35-255');
                        $i = $line;
                    } else {
                        echo '&nbsp;'.$plxPlugin->getLang('L_ANSWER').' : ';
                        plxUtils::printInput('r['.$i.']',$content,'text','35-255');
                        echo '</div>';
                        $i=0;
                    }
                }
            }
        }
        echo '<div class="inline">'.$plxPlugin->getLang('L_QUESTION').' : ';
        plxUtils::printInput('q[new]','','text','35-255');
         echo '&nbsp;'.$plxPlugin->getLang('L_ANSWER').' : ';
        plxUtils::printInput('r[new]','','text','35-255');
        echo '</div>
         <p>
        <input type="hidden" name="f" value="'.$name.'"/>
        <input type="submit" name="submit" value="'.$plxPlugin->getLang("L_REC").'"/>
        </p>
        </form>
        </div>';
    }

    echo '<form action="parametres_plugin.php?p=LionCaptcha" method="post" accept-charset="utf-8">
    <div class="inline">
    <h3>'.$plxPlugin->getLang('L_NEW_FILE').'</h3>';
    plxUtils::printSelect('newFile', plxUtils::getLangs());
    echo '</div>
    <p>
    <input type="submit" name="submit" value="'.$plxPlugin->getLang("L_REC").'"/>
    </p>
    </form>';
}
