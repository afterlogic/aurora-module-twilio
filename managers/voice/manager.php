<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
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

	/**
	 * @param \Aurora\System\Managers\GlobalManager &$oManager
	 */
	public function __construct(\Aurora\System\Managers\GlobalManager &$oManager, $sForcedStorage = '')
	{
		parent::__construct('voice', $oManager);

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
		$oApiFileCache = /* @var $oApiFileCache \Aurora\System\Managers\Filecache\Manager */\Aurora\System\Api::GetSystemManager('Filecache');
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
			$oApiFileCache = $bUseCache ? /* @var $oApiFileCache \Aurora\System\Managers\Filecache\Manager */\Aurora\System\Api::GetSystemManager('Filecache') : false;
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
