<?php

///////////////////////////////////////////////////////////////////////////////////

define("USER", trim(`whoami`));
define("DIR_HOME", "/home/" . USER . "/");
define("FILE_CFG", DIR_HOME . ".wpbysh/config/shwp_update");
$cmd = "cat /var/cpanel/users/" . USER . " | grep LOCALE |  sed s/LOCALE=//";
define("LOCALE", trim(`$cmd`));
$cmd = "cat /home/" . USER . "/.contactemail";
define("USER_EMAIL", trim(`$cmd`));
define("TIME_START", time());

///////////////////////////////////////////////////////////////////////////////////

if (isset($argv[1]))
{
	define("SINGLE_JOB", $argv[1]);
}
else
{
	define("SINGLE_JOB", false);
}

///////////////////////////////////////////////////////////////////////////////////

new WPAutoUpdate();

///////////////////////////////////////////////////////////////////////////////////

class WPAutoUpdate
{

	private $arrConfig = array();

	public function __construct()
	{
		if (!SINGLE_JOB)
		{
			$this->_loadConfig();
			///////////////////////////////////////////////////////////////////////////////////
			$this->_checkForUpdates();
			///////////////////////////////////////////////////////////////////////////////////
			$this->_processPendingUpdates();
			///////////////////////////////////////////////////////////////////////////////////
			$this->_notifyForPendingUpdates();
			///////////////////////////////////////////////////////////////////////////////////
			$this->_notifyForProcessedUpdates();
			///////////////////////////////////////////////////////////////////////////////////
			$this->_saveConfig();
		}
		else
		{
			$this->_loadConfig();
			$this->_processPendingUpdates();
			$this->_saveConfig();
		}
	}

	///////////////////////////////////////////////////////////////////////////////////

	private function _saveConfig()
	{
		file_put_contents(FILE_CFG, json_encode($this->arrConfig));
	}

	///////////////////////////////////////////////////////////////////////////////////

	private function _notifyForProcessedUpdates()
	{
		$arrNotifyCurrent	 = array();
		$arrNotifyWeekly	 = array();

		foreach ($this->arrConfig as $k => $v)
		{
			if (count($v['notify_queue']) == 0)
			{
				continue;
			}

			if (!$v['notify_update'])
			{
				$this->arrConfig[$k]['notify_queue'] = array();
				continue;
			}

			if ($v['notify_update'] == 'weekly')
			{
				if (date('w') == 1)
				{
					$arrTmp								 = array_flip($v['notify_queue']);
					$arrNotifyWeekly[$k]				 = array_intersect_key($v['update_log'], $arrTmp);
					$this->arrConfig[$k]['notify_queue'] = array();
				}

				continue;
			}

			$arrTmp								 = array_flip($v['notify_queue']);
			$arrNotify[$k]						 = array_intersect_key($v['update_log'], $arrTmp);
			$this->arrConfig[$k]['notify_queue'] = array();
		}

		if (count($arrNotify) != 0)
		{
			Email::NotifyProcessed($arrNotify);
		}

		if (count($arrNotifyWeekly) != 0)
		{
			Email::NotifyWeekly($arrNotifyWeekly);
		}

		///////////////////////////////////////////////////////////////////////////////////

		return;
	}

	///////////////////////////////////////////////////////////////////////////////////

	private function _processPendingUpdates()
	{
		foreach ($this->arrConfig as $k => $v)
		{
			$wpCli		 = WPCli::Init($k);
			$arrUpdate	 = array();

			foreach ($v['update_queue'] as $kk => $vv)
			{
				if (SINGLE_JOB)
				{
					if ($kk != SINGLE_JOB)
					{
						continue;
					}
				}
				else
				{
					if ($vv['date_start'] > TIME_START)
					{
						continue;
					}
				}

				///////////////////////////////////////////////////////////////////////////////////

				if ($vv['item_type'] == 'core')
				{
					$arrUpdate[$kk] = array(
						'item_type'		 => $vv['item_type'],
						'version_new'	 => $vv['version_new'],
						'item_name'		 => $vv['item_name'],
						'update_status'	 => 0,
					);

					if ($v['auto_update_core'])
					{
						$arrResult	 = $wpCli->UpdateCore($vv['version_new']);
						$arrResult	 = implode("\n", $arrResult);

						if (strpos($arrResult, "Success:") !== false)
						{
							$arrUpdate[$kk]['update_status'] = 1;
						}
					}
				}

				///////////////////////////////////////////////////////////////////////////////////

				if ($vv['item_type'] == 'plugin')
				{
					$arrUpdate[$kk] = array(
						'item_type'		 => $vv['item_type'],
						'version_new'	 => $vv['version_new'],
						'item_name'		 => $vv['item_name'],
						'update_status'	 => 0,
					);

					if ($v['auto_update_plugin'])
					{
						$arrResult	 = $wpCli->UpdatePlugin($vv['item_name'], $vv['version_new']);
						$arrResult	 = implode("\n", $arrResult);

						if (strpos($arrResult, "Success:") !== false)
						{
							$arrUpdate[$kk]['update_status'] = 1;
						}
					}
				}

				///////////////////////////////////////////////////////////////////////////////////

				if ($vv['item_type'] == 'theme')
				{
					$arrUpdate[$kk] = array(
						'item_type'		 => $vv['item_type'],
						'version_new'	 => $vv['version_new'],
						'item_name'		 => $vv['item_name'],
						'update_status'	 => 0,
					);

					if ($v['auto_update_theme'])
					{
						$arrResult	 = $wpCli->UpdateTheme($vv['item_name'], $vv['version_new']);
						$arrResult	 = implode("\n", $arrResult);

						if (strpos($arrResult, "Success:") !== false)
						{
							$arrUpdate[$kk]['update_status'] = 1;
						}
					}
				}

				///////////////////////////////////////////////////////////////////////////////////
			}

			if (count($arrUpdate) == 0)
			{
				continue;
			}

			$this->arrConfig[$k]['update_log'][TIME_START]	 = $arrUpdate;
			$this->arrConfig[$k]['update_queue']			 = array_diff_key($this->arrConfig[$k]['update_queue'], $arrUpdate);
			if (!SINGLE_JOB)
			{
				$this->arrConfig[$k]['notify_queue'][] = TIME_START;
			}
		}

		return;
	}

	///////////////////////////////////////////////////////////////////////////////////

	private function _notifyForPendingUpdates()
	{
		$arrNotify = LibArray::GroupBy($this->arrConfig, 'notify_pending_update', true);

		if (!isset($arrNotify[1]))
		{
			return;
		}

		$arrNotify = $arrNotify[1];

		///////////////////////////////////////////////////////////////////////////////////

		foreach ($arrNotify as $k => $v)
		{
			unset($arrNotify[$k]['update_log']);

			if (count($v['update_queue']) == 0)
			{
				unset($arrNotify[$k]);
				continue;
			}

			///////////////////////////////////////////////////////////////////////////////////
			foreach ($v['update_queue'] as $kk => $vv)
			{
				if ($vv['notified'])
				{
					unset($arrNotify[$k]['update_queue'][$kk]);
					continue;
				}

				$this->arrConfig[$k]['update_queue'][$kk]['notified'] ++;
			}

			if (count($arrNotify[$k]['update_queue']) == 0)
			{
				unset($arrNotify[$k]);
				continue;
			}
		}

		///////////////////////////////////////////////////////////////////////////////////

		if (count($arrNotify) > 0)
		{
			Email::NotifyPending($arrNotify);
		}

		///////////////////////////////////////////////////////////////////////////////////

		return;
	}

	///////////////////////////////////////////////////////////////////////////////////

	private function _checkForUpdates()
	{
		foreach ($this->arrConfig as $path => $arrConfig)
		{
			$arrUpdateQueue = array();

			$wpCli = WPCli::Init($path);

			///////////////////////////////////////////////////////////////////////////////////
			// [Core]

			if ($arrConfig['auto_update_core'])
			{
				$arrUpdate = $wpCli->CheckCoreUpdate();

				if (count($arrUpdate) > 0)
				{
					$arrUpdateQueue[md5($path . 'core' . $arrUpdate[0]['version'])] = array(
						'item_type'		 => 'core',
						'version_new'	 => $arrUpdate[0]['version'],
						'item_name'		 => '-',
						'notified'		 => 0,
						'date_start'	 => ((TIME_START - (10 * 60)) + 24 * 60 * 60),
					);
				}
			}

			///////////////////////////////////////////////////////////////////////////////////
			// [Plugin]

			if ($arrConfig['auto_update_plugin'])
			{
				$arrUpdate = $wpCli->CheckPluginUpdate();

				if (count($arrUpdate) > 0)
				{
					foreach ($arrUpdate as $k => $v)
					{
						$arrUpdateQueue[md5($path . $v['name'] . $arrUpdate[0]['update_version'])] = array(
							'item_type'		 => 'plugin',
							'version_new'	 => $v['update_version'],
							'item_name'		 => $v['name'],
							'notified'		 => 0,
							'date_start'	 => ((TIME_START - (10 * 60)) + 24 * 60 * 60),
						);
					}
				}
			}

			///////////////////////////////////////////////////////////////////////////////////
			// [Theme]

			if ($arrConfig['auto_update_theme'])
			{
				$arrUpdate = $wpCli->CheckThemeUpdate();

				if (count($arrUpdate) > 0)
				{
					foreach ($arrUpdate as $k => $v)
					{
						$arrUpdateQueue[md5($path . $v['name'] . $arrUpdate[0]['update_version'])] = array(
							'item_type'		 => 'theme',
							'version_new'	 => $v['update_version'],
							'item_name'		 => $v['name'],
							'notified'		 => 0,
							'date_start'	 => ((TIME_START - (10 * 60)) + 24 * 60 * 60),
						);
					}
				}
			}

			///////////////////////////////////////////////////////////////////////////////////


			foreach ($arrUpdateQueue as $k => $v)
			{
				if (!isset($this->arrConfig[$path]['update_queue'][$k]))
				{
					$this->arrConfig[$path]['update_queue'][$k] = $v;
				}
			}
		}
		return;
	}

	///////////////////////////////////////////////////////////////////////////////////

	private function _loadConfig()
	{
		if (!file_exists(FILE_CFG))
		{
			exit;
		}

		$this->arrConfig = (array) json_decode(file_get_contents(FILE_CFG), 1);

		if (count($this->arrConfig) == 0)
		{
			exit;
		}

		return;
	}

	///////////////////////////////////////////////////////////////////////////////////
}

///////////////////////////////////////////////////////////////////////////////////

class Email
{

	public static function NotifyWeekly($arrNotify)
	{
		$subject = LibLocale::Get("mail_weekly_notify_subj");
		$body	 = LibLocale::Get("mail_weekly_notify_header");

		///////////////////////////////////////////////////////////////////////////////////

		foreach ($arrNotify as $path => $arrUpdate)
		{
			$body.= sprintf(LibLocale::Get('instalaciq'), $path);

			$arrData = array();

			foreach ($arrUpdate as $k => $v)
			{
				$arrData = array_merge($arrData, $v);
			}

			$arrData = LibArray::GroupBy($arrData, 'item_type');

			foreach ($arrData as $k => $v)
			{
				$arrData[$k] = array_values($v);
			}

			if (isset($arrData['core']))
			{
				$body .= sprintf(LibLocale::Get("obnoviavane_na_iadroto"), $arrData['core'][0]['version_new']);
			}

			///////////////////////////////////////////////////////////////////////////////////

			if (isset($arrData['plugin']))
			{
				$body .= LibLocale::Get("obnoviavane_na_plugini");

				foreach ($arrData['plugin'] as $k => $v)
				{
					$body .= sprintf(LibLocale::Get("plugin_kym_versia"), $v['item_name'], $v['version_new']);
				}
			}

			///////////////////////////////////////////////////////////////////////////////////

			if (isset($arrData['theme']))
			{
				$body .= LibLocale::Get("obnoviavane_na_temi");

				foreach ($arrData['theme'] as $k => $v)
				{
					$body .= sprintf(LibLocale::Get("tema_kym_versia"), $v['item_name'], $v['version_new']);
				}
			}
		}

		///////////////////////////////////////////////////////////////////////////////////

		$body .= LibLocale::Get("mail_weekly_notify_footer");

		///////////////////////////////////////////////////////////////////////////////////

		self::_send($subject, $body);
	}

	public static function NotifyProcessed($arrNotify)
	{
		$subject = LibLocale::Get("mail_processed_notify_subj");
		$body	 = LibLocale::Get("mail_processed_notify_header");

		///////////////////////////////////////////////////////////////////////////////////

		foreach ($arrNotify as $path => $arrUpdate)
		{
			$body.= sprintf(LibLocale::Get('instalaciq'), $path);

			$arrData = array();

			foreach ($arrUpdate as $k => $v)
			{
				$arrData = array_merge($arrData, $v);
			}

			$arrData = LibArray::GroupBy($arrData, 'item_type');

			foreach ($arrData as $k => $v)
			{
				$arrData[$k] = array_values($v);
			}

			if (isset($arrData['core']))
			{
				$body .= sprintf(LibLocale::Get("obnoviavane_na_iadroto"), $arrData['core'][0]['version_new']);
			}

			///////////////////////////////////////////////////////////////////////////////////

			if (isset($arrData['plugin']))
			{
				$body .= LibLocale::Get("obnoviavane_na_plugini");

				foreach ($arrData['plugin'] as $k => $v)
				{
					$body .= sprintf(LibLocale::Get("plugin_kym_versia"), $v['item_name'], $v['version_new']);
				}
			}

			///////////////////////////////////////////////////////////////////////////////////

			if (isset($arrData['theme']))
			{
				$body .= LibLocale::Get("obnoviavane_na_temi");

				foreach ($arrData['theme'] as $k => $v)
				{
					$body .= sprintf(LibLocale::Get("tema_kym_versia"), $v['item_name'], $v['version_new']);
				}
			}
		}

		///////////////////////////////////////////////////////////////////////////////////

		$body .= LibLocale::Get("mail_processed_notify_footer");

		///////////////////////////////////////////////////////////////////////////////////

		self::_send($subject, $body);
	}

	public static function NotifyPending($arrNotify)
	{
		$subject = LibLocale::Get("mail_pending_notify_subj");
		$body	 = LibLocale::Get("mail_pending_notify_header");

		///////////////////////////////////////////////////////////////////////////////////
		foreach ($arrNotify as $path => $arrData)
		{
			$arrData = LibArray::GroupBy($arrData['update_queue'], 'item_type');

			$body.= sprintf(LibLocale::Get('instalaciq'), $path);

			if (isset($arrData['core']))
			{
				$body .= sprintf(LibLocale::Get("obnoviavane_na_iadroto"), $arrData['core'][0]['version_new']);
			}

			///////////////////////////////////////////////////////////////////////////////////

			if (isset($arrData['plugin']))
			{
				$body .= LibLocale::Get("obnoviavane_na_plugini");

				foreach ($arrData['plugin'] as $k => $v)
				{
					$body .= sprintf(LibLocale::Get("plugin_kym_versia"), $v['item_name'], $v['version_new']);
				}
			}

			///////////////////////////////////////////////////////////////////////////////////

			if (isset($arrData['theme']))
			{
				$body .= LibLocale::Get("obnoviavane_na_temi");

				foreach ($arrData['theme'] as $k => $v)
				{
					$body .= sprintf(LibLocale::Get("tema_kym_versia"), $v['item_name'], $v['version_new']);
				}
			}
		}

		///////////////////////////////////////////////////////////////////////////////////

		$body .= LibLocale::Get("mail_pending_notify_footer");

		///////////////////////////////////////////////////////////////////////////////////

		self::_send($subject, $body);
	}

	///////////////////////////////////////////////////////////////////////////////////
	//TODO
	private static function _send($subject, $body)
	{
		$arrHeader	 = array(
			"Content-Type: text/plain; charset=utf-8",
			"Content-Transfer-Encoding: quoted-printable",
//			"From: support@superhosting.bg",
			"X-Mailer: PHP/" . phpversion(),
		);
		$subject	 = '=?utf-8?B?' . base64_encode($subject) . '?=';
		mail(USER_EMAIL, $subject, $body, implode("\r\n", $arrHeader));
	}

}

///////////////////////////////////////////////////////////////////////////////////

class LibLocale
{

	private static $arrLocale = array(
		'bg' => array(
			'mail_weekly_notify_subj'		 => "Wordpress - Приложени ъпдейти през седмицата",
			'mail_weekly_notify_header'		 => "Здравейте,
През изминалата седмица бяха приложени следните ъпдейти върху управлявани от Вас WordPress сайтове.

Ъпдейта включва:

",
			'mail_weekly_notify_footer'		 => "
Проверете дали сайтовете, темите и плъгините работят коректно след ъпдейтите.

С уважение,
    СуперХостинг.БГ
    www.superhosting.bg | www.blog.superhosting.bg
    Помощна страница: www.help.superhosting.bg
Адрес:
    гр.София,
    кв. Изток, бул. Д-р Г. М. Димитров №36
Тел:
    0700 45 800 | +359 88 55 888 22

НОВО ПОКОЛЕНИЕ ХОСТИНГ
",
			'mail_pending_notify_subj'		 => "Wordpress - Налични ъпдейти",
			///////////////////////////////////////////////////////////////////////////////////
			'mail_pending_notify_header'	 => "Здравейте,
Налични са ъпдейти за администрираните от Вас WordPress сайтове, и ще
бъдат приложени автоматично след 24 часа.

Ъпдейта включва:

",
			///////////////////////////////////////////////////////////////////////////////////
			'mail_pending_notify_footer'	 => "
Ако не желаете някой от сайтовете или плъгините/темите в тях да бъдат ъпдейтнати,
може да изключите опцията през плъгина WordPress by SuperHosting в контролния cPanel
на Вашия хостинг акаунт.

С уважение,
    СуперХостинг.БГ
    www.superhosting.bg | www.blog.superhosting.bg
    Помощна страница: www.help.superhosting.bg
Адрес:
    гр.София,
    кв. Изток, бул. Д-р Г. М. Димитров №36
Тел:
    0700 45 800 | +359 88 55 888 22

НОВО ПОКОЛЕНИЕ ХОСТИНГ
",
			///////////////////////////////////////////////////////////////////////////////////
			'obnoviavane_na_iadroto'		 => "    Обняване на ядрото към версия: %s\n",
			///////////////////////////////////////////////////////////////////////////////////
			'obnoviavane_na_plugini'		 => "    Обновяване на плъгини:\n",
			///////////////////////////////////////////////////////////////////////////////////
			'plugin_kym_versia'				 => "        %s към версия %s\n",
			///////////////////////////////////////////////////////////////////////////////////
			'obnoviavane_na_temi'			 => "    Обновяване на теми:\n",
			///////////////////////////////////////////////////////////////////////////////////
			'tema_kym_versia'				 => "        %s към версия %s\n",
			///////////////////////////////////////////////////////////////////////////////////
			'instalaciq'					 => "За инсталация [%s]:\n",
			///////////////////////////////////////////////////////////////////////////////////
			'mail_processed_notify_subj'	 => "Wordpress - Приложени ъпдейти",
			///////////////////////////////////////////////////////////////////////////////////
			'mail_processed_notify_header'	 => "Здравейте,
Бяха приложени ъпдейти за администрираните от Вас WordPress сайтове.

Ъпдейта включва:

",
			///////////////////////////////////////////////////////////////////////////////////
			'mail_processed_notify_footer'	 => "
Проверете дали всичко по сайтовете е ОК!

С уважение,
    СуперХостинг.БГ
    www.superhosting.bg | www.blog.superhosting.bg
    Помощна страница: www.help.superhosting.bg
Адрес:
    гр.София,
    кв. Изток, бул. Д-р Г. М. Димитров №36
Тел:
    0700 45 800 | +359 88 55 888 22

НОВО ПОКОЛЕНИЕ ХОСТИНГ
",
		),
	);

	public static function Get($key)
	{
		if (isset(self::$arrLocale[LOCALE][$key]))
		{
			return self::$arrLocale[LOCALE][$key];
		}

		return '';
	}

}

///////////////////////////////////////////////////////////////////////////////////

class LibArray
{

	public static function GroupBy($array, $key, $preserveKeys = false)
	{
		$arrRes = array();

		foreach ($array as $k => $v)
		{
			if (!isset($arrRes[$v[$key]]))
			{
				$arrRes[$v[$key]] = array();
			}

			if (isset($v[$key]))
			{
				if ($preserveKeys)
				{
					$arrRes[$v[$key]][$k] = $v;
				}
				else
				{
					$arrRes[$v[$key]][] = $v;
				}
			}
		}

		return $arrRes;
	}

}

///////////////////////////////////////////////////////////////////////////////////

class WPCli
{

	private $path = '';

	public static function Init($path)
	{
		return new WPCli($path);
	}

	///////////////////////////////////////////////////////////////////////////////////

	public function __construct($path)
	{
		$this->path = $path;
	}

	///////////////////////////////////////////////////////////////////////////////////

	public function UpdateTheme($theme, $version = '')
	{
		$arrParam = array(
			'theme',
			'update',
			$theme,
		);

		if ($version != '')
		{
			$arrParam[] = '--version=' . $version;
		}

		return $this->_exec($arrParam);
	}

	///////////////////////////////////////////////////////////////////////////////////

	public function UpdatePlugin($plugin, $version = '')
	{
		$arrParam = array(
			'plugin',
			'update',
			$plugin,
		);

		if ($version != '')
		{
			$arrParam[] = '--version=' . $version;
		}

		return $this->_exec($arrParam);
	}

	///////////////////////////////////////////////////////////////////////////////////

	public function UpdateCore($version = '')
	{
		$arrParam = array(
			'core',
			'update',
			'--force',
		);

		if ($version != '')
		{
			$arrParam[] = '--version=' . escapeshellarg($version);
		}

		///////////////////////////////////////////////////////////////////////////////////

		return $this->_exec($arrParam);
	}

	///////////////////////////////////////////////////////////////////////////////////

	public function CheckThemeUpdate()
	{
		$arrParam = array(
			'theme',
			'update',
			'--dry-run',
			'--all',
		);

		$arrUpdate = $this->_exec($arrParam);

		if (trim($arrUpdate[0]) != 'Available theme updates:')
		{
			return array();
		}

		unset($arrUpdate[0]);
		$arrUpdate = array_values($arrUpdate);

		$arrUpdate	 = implode("\n", $arrUpdate);
		$arrUpdate	 = preg_replace("/[ \t]/", " ", $arrUpdate);
		$arrUpdate	 = explode("\n", $arrUpdate);

		$arrKeys = explode(" ", $arrUpdate[0]);

		foreach ($arrKeys as $k => $v)
		{
			if (trim($v) != '')
			{
				$arrKeys[$k] = trim($v);
			}
		}

		$arrKeys = array_values($arrKeys);

		unset($arrUpdate[0]);
		$arrUpdate = array_values($arrUpdate);

		$arrResult = array();

		foreach ($arrUpdate as $k => $v)
		{
			if ($k % 2)
			{
				continue;
			}

			$v = explode(" ", $v);

			foreach ($v as $kk => $vv)
			{
				if (trim($vv) != '')
				{
					$v[$kk] = trim($vv);
				}
			}

			$v = array_values($v);

			$row = array();

			foreach ($v as $kk => $vv)
			{
				$row[$arrKeys[$kk]] = $vv;
			}

			$arrResult[] = $row;
		}

		if (count($arrResult) == 0)
		{
			return false;
		}

		return $arrResult;
	}

	///////////////////////////////////////////////////////////////////////////////////

	public function CheckPluginUpdate()
	{
		$arrParam = array(
			'plugin',
			'update',
			'--dry-run',
			'--all',
		);

		$arrUpdate = $this->_exec($arrParam);

		if (trim($arrUpdate[0]) != 'Available plugin updates:')
		{
			return array();
		}

		unset($arrUpdate[0]);
		$arrUpdate = array_values($arrUpdate);

		$arrUpdate	 = implode("\n", $arrUpdate);
		$arrUpdate	 = preg_replace("/[ \t]/", " ", $arrUpdate);
		$arrUpdate	 = explode("\n", $arrUpdate);

		$arrKeys = explode(" ", $arrUpdate[0]);

		foreach ($arrKeys as $k => $v)
		{
			if (trim($v) != '')
			{
				$arrKeys[$k] = trim($v);
			}
		}

		$arrKeys = array_values($arrKeys);

		unset($arrUpdate[0]);
		$arrUpdate = array_values($arrUpdate);

		$arrResult = array();

		foreach ($arrUpdate as $k => $v)
		{
			if ($k % 2)
			{
				continue;
			}

			$v = explode(" ", $v);

			foreach ($v as $kk => $vv)
			{
				if (trim($vv) != '')
				{
					$v[$kk] = trim($vv);
				}
			}

			$v = array_values($v);

			$row = array();

			foreach ($v as $kk => $vv)
			{
				$row[$arrKeys[$kk]] = $vv;
			}

			$arrResult[] = $row;
		}

		if (count($arrResult) == 0)
		{
			return false;
		}

		return $arrResult;
	}

	///////////////////////////////////////////////////////////////////////////////////

	public function CheckCoreUpdate()
	{
		$arrParam = array(
			'core',
			'check-update',
			'--format=json',
		);

		$arrUpdate	 = $this->_exec($arrParam);
		$arrUpdate	 = (array) json_decode($arrUpdate[0], 1);

		if (count($arrUpdate) == 0)
		{
			return array();
		}

		return $arrUpdate;
	}

	///////////////////////////////////////////////////////////////////////////////////

	private function _exec($arrParam)
	{
		foreach ($arrParam as $k => $v)
		{
			$arrParam[$k] = escapeshellarg($v);
		}

		$arrParam	 = array_reverse($arrParam);
		$arrParam[]	 = "/usr/local/bin/wp-cli";
		$arrParam	 = array_reverse($arrParam);
		$arrParam[]	 = ' --path=' . escapeshellarg($this->path);
		$arrParam[]	 = "2>&1";

		$cmd = implode(' ', $arrParam);

		$arrResult = array();

		exec($cmd, $arrResult);

		return (array) $arrResult;
	}

	///////////////////////////////////////////////////////////////////////////////////
}

?>