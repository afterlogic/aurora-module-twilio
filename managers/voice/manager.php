<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 *
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

/**
 * @package Voice
 */
class CApiVoiceManager extends \Aurora\System\Managers\AbstractManager
{
	/**
	 * @var $oApiContactsManager CApiContactsContactsManager
	 */
	private $oApiContactsManager;

	/*
	 * @var $oApiGContactsManager CApiGcontactsManager
	 */
	private $oApiGContactsManager;

	public function __construct($sForcedStorage = '')
	{
		parent::__construct('voice');

		$this->oApiContactsManager =\Aurora\System\Api::Manager('contactsmain');
		$this->oApiGContactsManager =\Aurora\System\Api::Manager('gcontacts');
	}

	/**
	 * @param int $iIdUser
	 * @return string
	 */
	private function _generateCacheFileName($iIdUser)
	{
		return 0 < $iIdUser ? implode('-', array('user-contacts', $iIdUser, 'callers-names.json')) : '';
	}

	/**
	 * @param int $iIdUser
	 */
	public function flushCallersNumbersCache($iIdUser)
	{
		$sCacheKey = $this->_generateCacheFileName($iIdUser);
		$oApiFileCache = /* @var $oApiFileCache \Aurora\System\Managers\Filecache */new \Aurora\System\Managers\Filecache();
//		$oApiUsers = /* @var $oApiUsers \CApiUsersManager */\Aurora\System\Api::GetSystemManager('users');
		
		if ($oApiFileCache && $oApiUsers && !empty($sCacheKey))
		{
			$oAccount = $oApiUsers->getDefaultAccount($iIdUser);
			if ($oAccount)
			{
				$oApiFileCache->clear($oAccount, $sCacheKey);
				\Aurora\System\Api::Log('Cache: clear contacts names cache');
			}
		}
	}

	/**
	 * @param CAccount $oAccount
	 * @param array $aNumbers
	 * @param bool $bUseCache = true
	 * @return array
	 */
	public function getNamesByCallersNumbers($oAccount, $aNumbers, $bUseCache = true)
	{
		$mResult = false;
		$oApiContactsManager =\Aurora\System\Api::Manager('contactsmain');
		if (is_array($aNumbers) && 0 < count($aNumbers) && $oAccount && $oApiContactsManager)
		{
			$bFromCache = false;
			$sCacheKey = '';
			$mNamesResult = null;
			$oApiFileCache = $bUseCache ? /* @var $oApiFileCache \Aurora\System\Managers\Filecache */new \Aurora\System\Managers\Filecache() : false;
			if ($oApiFileCache)
			{
				$sCacheKey = $this->_generateCacheFileName($oAccount->IdUser);
				if (!empty($sCacheKey))
				{
					$sData = $oApiFileCache->get($oAccount, $sCacheKey);
					if (!empty($sData))
					{
						$mNamesResult = @json_decode($sData, true);
						if (!is_array($mNamesResult))
						{
							$mNamesResult = null;
						}
						else
						{
							$bFromCache = true;
							\Aurora\System\Api::Log('Cache: get contacts names from cache (count:'.count($mNamesResult).')');
						}
					}
				}
			}
			
			if (!is_array($mNamesResult))
			{
				$mNamesResult = $oApiContactsManager->GetAllContactsNamesWithPhones($oAccount);
			}

			if (is_array($mNamesResult))
			{
				if (!$bFromCache && $oApiFileCache && 0 < strlen($sCacheKey))
				{
					$oApiFileCache->put($oAccount, $sCacheKey, @json_encode($mNamesResult));
					\Aurora\System\Api::Log('Cache: save contacts names to cache (count:'.count($mNamesResult).')');
				}

				$aNormNumbers = array();
				foreach ($aNumbers as $sNumber)
				{
					$aNormNumbers[$sNumber] = \Aurora\System\Utils::ClearPhone($sNumber);
				}

				foreach ($aNormNumbers as $sInputNumber => $sClearNumber)
				{
					$aNormNumbers[$sInputNumber] = isset($mNamesResult[$sClearNumber])
						? $mNamesResult[$sClearNumber] : '';
				}

				$mResult = $aNormNumbers;
			}
		}
		else if (is_array($aNumbers))
		{
			$mResult = array();
		}

		return $mResult;
	}
}
