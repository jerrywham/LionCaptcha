<?php
/**
* Plugin LionCaptcha
*
* @package	PLX
* @version	1.0
* @date	08/08/17
* @author Cyril MAGUIRE
**/

define('DS',DIRECTORY_SEPARATOR);
class LionCaptcha extends plxPlugin {

	public $captcha = null;
	private $infos = array();

	public function __construct($default_lang) {
		# appel du constructeur de la classe plxPlugin (obligatoire)
		parent::__construct($default_lang);

		# limite l'acces a l'ecran de configuration du plugin
		# PROFIL_ADMIN , PROFIL_MANAGER , PROFIL_MODERATOR , PROFIL_EDITOR , PROFIL_WRITER
		$this->setConfigProfil(PROFIL_ADMIN);

		if ($this->getParam('dir') != '') {
			//Pensez à changer également le nom du dossier s'il existe
			if (!defined('LION_CAPTCHA')) {
				define('LION_CAPTCHA',$this->getParam('dir'));
			}

			$this->captcha = new Captcha($default_lang,$_SESSION);
			
			# Declaration d'un hook (existant ou nouveau)
			$this->addHook('plxShowCapchaQ','plxShowCapchaQ');
			$this->addHook('plxShowCapchaR', 'plxShowCapchaR');
			$this->addHook('plxMotorNewCommentaire','plxMotorNewCommentaire');
			$this->addHook('plxMotorDemarrageNewCommentaire','plxMotorDemarrageNewCommentaire');
		}
	}

	# Activation / desactivation
	public function OnActivate() {
		# code à executer à l’activation du plugin
	}
	public function OnDeactivate() {
		# code à executer à la désactivation du plugin
	}
	

	########################################
	# HOOKS
	########################################

	public function plxShowCapchaQ()
	{
		$this->captcha->actionBegin();
		$template = $this->captcha->template($this->getParam('genererInput'));
		$string =<<<END
			echo '$template';
			return true;
END;
		echo '<?php '.$string.'?>';
	}

	/**
	 * Méthode qui retourne la réponse du capcha // obsolète
	 *
	 * @return	stdio
	 * @author	Stéphane F.
	 **/
	public function plxShowCapchaR() {
		echo '<?php return true; ?>';  # pour interrompre la fonction CapchaR de plxShow
	}


	public function plxMotorNewCommentaire()
	{
		$string = "
		return true;
		";
		
		echo '<?php '.$string.'?>';
	}

	public function plxMotorDemarrageNewCommentaire()
	{
		$string = "
		\$artId = \$this->cible;
		\$content = plxUtils::unSlash(\$_POST);
		\$plxPlugin = \$this->plxPlugins->getInstance('LionCaptcha');
		if(strtolower(\$_SERVER['REQUEST_METHOD'])!= 'post' OR \$this->aConf['capcha'] AND (!isset(\$_POST['qid']) )) {
			\$retour = L_NEWCOMMENT_ERR_ANTISPAM;
		} else {

			# On vérifie que le capcha est correct
			if(\$this->aConf['capcha'] == 0 OR  \$plxPlugin->captcha->actionBegin() === true) {
				if(!empty(\$content['name']) AND !empty(\$content['content'])) { # Les champs obligatoires sont remplis
					\$comment=array();
					\$comment['type'] = 'normal';
					\$comment['author'] = plxUtils::strCheck(trim(\$content['name']));
					\$comment['content'] = plxUtils::strCheck(trim(\$content['content']));
					# On vérifie le mail
					\$comment['mail'] = (plxUtils::checkMail(trim(\$content['mail'])))?trim(\$content['mail']):'';
					# On vérifie le site
					\$comment['site'] = (plxUtils::checkSite(\$content['site'])?\$content['site']:'');
					# On récupère l'adresse IP du posteur
					\$comment['ip'] = plxUtils::getIp();
					# index du commentaire
					\$idx = \$this->nextIdArtComment(\$artId);
					# Commentaire parent en cas de réponse
					if(isset(\$content['parent']) AND !empty(\$content['parent'])) {
						\$comment['parent'] = intval(\$content['parent']);
					} else {
						\$comment['parent'] = '';
					}
					# On génère le nom du fichier
					\$time = time();
					if(\$this->aConf['mod_com']) # On modère le commentaire => underscore
						\$comment['filename'] = '_'.\$artId.'.'.\$time.'-'.\$idx.'.xml';
					else # On publie le commentaire directement
						\$comment['filename'] = \$artId.'.'.\$time.'-'.\$idx.'.xml';
					# On peut créer le commentaire
					if(\$this->addCommentaire(\$comment)) { # Commentaire OK
						if(\$this->aConf['mod_com']) # En cours de modération
							\$retour = 'mod';
						else # Commentaire publie directement, on retourne son identifiant
							\$retour = 'c'.\$artId.'-'.\$idx;
					} else { # Erreur lors de la création du commentaire
						\$retour = L_NEWCOMMENT_ERR;
					}
				} else { # Erreur de remplissage des champs obligatoires
					\$retour = L_NEWCOMMENT_FIELDS_REQUIRED;
				}
			} else { # Erreur de vérification capcha
				\$retour = L_NEWCOMMENT_ERR_ANTISPAM;
			}
		}
		";
		echo '<?php '.$string.'?>';
	}
	

}

/**
* CAPTCHA
* D'après LionWiki 3.2.9, (c) Adam Zivner, licensed under GNU/GPL v2 (Plugin Captcha)
*/
class Captcha {

	private $question_file;
	public $session;
	
	public function __construct($lang,$session) {
		$this->session = $session;

		$this->question_file = PLX_ROOT.LION_CAPTCHA.DS;

		if (!is_dir($this->question_file)) {
			@mkdir($this->question_file);
		}
		$this->mkFrQuest();
		$this->mkEnQuest();

		if(file_exists($this->question_file.$lang."_questions.txt"))
			$this->question_file .= $lang."_questions.txt";
		else
			$this->question_file .= "en_questions.txt";

	}

	private function mkFrQuest() {
		$txt = "# File for turing test questions. Structure of the file is very simple, first\n";
		$txt .= "# line of a record is \"--\" which indicates new record (question). Second line\n";
		$txt .= "# is question and third line is right answer. You can add more answers to third\n";
		$txt .= "# separated by comma. Everything else is ignored, so you can use it as comments.\n";
		$txt .= "# In that case, please use something like \"#\" or \"//\" to make it clear it\n";
		$txt .= "# is comment. Comparing answers is case insensitive.\n";
		$txt .= "\n";
		$txt .= "--\n";
		$txt .= "De quelle couleur est le citron?\n";
		$txt .= "jaune\n";
		$txt .= "\n";
		$txt .= "--\n";
		$txt .= "Combien font 4 fois 4?\n";
		$txt .= "16, seize\n";
		$txt .= "\n";
		$txt .= "--\n";
		$txt .= "chat = Tom, souris = ?\n";
		$txt .= "jerry\n";
		$txt .= "\n";
		$txt .= "--\n";
		$txt .= "On prend la température avec un ...?\n";
		$txt .= "thermomètre, thermometre, termometre, termomètre\n";
		$txt .= "--\n";
		$txt .= "Corrigez le mot : aurtografe\n";
		$txt .= "orthographe\n";
		$txt .= "\n";
		$txt .= "--\n";
		$txt .= "22 moins 17?\n";
		$txt .= "5, cinq\n";
		$txt .= "\n";
		$txt .= "--\n";
		$txt .= "Je pense donc je ... ?\n";
		$txt .= "suis\n";
		$txt .= "\n";
		$txt .= "--\n";
		$txt .= "Prénom d'Einstein?\n";
		$txt .= "Albert\n";
		$txt .= "\n";
		$txt .= "--\n";
		$txt .= "Qui est le frère de Mario ?\n";
		$txt .= "luiggi, luigi, louiji, luiji\n";
		$txt .= "\n";
		$txt .= "--\n";
		$txt .= "Où se trouve la Tour Eiffel ?\n";
		$txt .= "Paris";

		if(!file_exists($this->question_file."fr_questions.txt")) {
			file_put_contents($this->question_file."fr_questions.txt", $txt);
		}
		if(!file_exists($this->question_file.'index.html')) {
			file_put_contents($this->question_file.'index.html', '');
		}
	}
	private function mkEnQuest() {
		$txt = "# File for turing test questions. Structure of the file is very simple, first\n";
		$txt .= "# line of a record is \"--\" which indicates new record (question). Second line\n";
		$txt .= "# is question and third line is right answer. You can add more answers to third\n";
		$txt .= "# separated by comma. Everything else is ignored, so you can use it as comments.\n";
		$txt .= "# In that case, please use something like \"#\" or \"//\" to make it clear it\n";
		$txt .= "# is comment. Comparing answers is case insensitive.\n";
		$txt .= "\n";
		$txt .= "--\n";
		$txt .= "What color is lemon?\n";
		$txt .= "Yellow\n";
		$txt .= "\n";
		$txt .= "--\n";
		$txt .= "How much is 4 times 4?\n";
		$txt .= "16, sixteen\n";
		$txt .= "\n";
		$txt .= "--\n";
		$txt .= "cat - Tom, mouse -\n";
		$txt .= "Jerry\n";
		$txt .= "\n";
		$txt .= "--\n";
		$txt .= "Shortcut of World War 2?\n";
		$txt .= "WW2, WWII\n";
		$txt .= "\n";
		$txt .= "--\n";
		$txt .= "Correct spelling: univrsity\n";
		$txt .= "University\n";
		$txt .= "\n";
		$txt .= "--\n";
		$txt .= "Difference between 22 and 17?\n";
		$txt .= "5, five\n";
		$txt .= "\n";
		$txt .= "--\n";
		$txt .= "I think, therefore I...\n";
		$txt .= "am\n";
		$txt .= "\n";
		$txt .= "--\n";
		$txt .= "First name of Einstein?\n";
		$txt .= "Albert\n";
		$txt .= "\n";
		$txt .= "--\n";
		$txt .= "How many moons has the Earth?\n";
		$txt .= "1, one\n";
		$txt .= "\n";
		$txt .= "--\n";
		$txt .= "Name of partner of Eve of Eden?\n";
		$txt .= "Adam";

		if(!file_exists($this->question_file."en_questions.txt")) {
			file_put_contents($this->question_file."en_questions.txt", $txt);
		}
	}
	public function actionBegin() {
		if(isset($_REQUEST["qid"])) {
			return $this->checkCaptcha();
		}
	}

	/*
	 * Functions return number of questions in question file. Method is very simple, it just counts
	 * number of occurence of "--" at the begining of the line.
	 */

	private function questionCount() {
		$count = 0;
		$q = fopen($this->question_file, "r");

		if(!$q) {
			return 'Error';
		}

		while($line = fgets($q))
			if(!strcmp(trim($line), "--"))
				$count++;
		fclose($q);
		return $count;
	}

	/*
	 * Function returns $line. line of $i. question. Convention is that first line is question and
	 * second line is answer(s). Numbering is Pascal-like, that means that getQuestion(1, 1) returns 1. line of 1. question.
	 */

	private function getQuestion($i, $line) {
		$count = 0;
		$q = fopen($this->question_file, "r");
		if(!$q) {
			//return 0; // Oops
			return 'Error';
		}
		$str = "";

		while($l = fgets($q)) {
			if(!strcmp(trim($l), "--")) {
				$count++;
				if($count == $i) {
					for($k = 0, $str = ""; $k < $line && $str = fgets($q); $k++);
					break;
				}
			}
		}
		fclose($q);
		return str_replace("'","\'",$str);
	}

	private function checkCaptcha() {
		$question_id = $_REQUEST["qid"];
		$answer = trim($_REQUEST["rep"]);

		// if(empty($question_id) || empty($answer) || !is_numeric($question_id))
		// 	return true;

		$right_answers = explode(",", $this->getQuestion($question_id, 2));

		$equals = false;

		foreach($right_answers as $a) {
			if(!strcasecmp(trim($a), $answer)) {
				$equals = true;
				break;
			}
		}

		return $equals;
	}

	public function template($genererInput=0) {
		$question_count = $this->questionCount();
		$question_id = rand(1, $question_count);
		$question_text = trim($this->getQuestion($question_id, 1));

		$html = "<span id=\"captcha-question\">" . $question_text . "</span>";
		$html .= "<input type=\"hidden\" id=\"captcha-id\" name=\"qid\" value=\"$question_id\" />";
		if ($genererInput == 1) {
			$html .= "<input type=\"text\" id=\"captcha-input\" name=\"rep\" class=\"input input-success success\" value=\"\" autocomplete=\"off\" />";
		}
		return $html;
	}
}
