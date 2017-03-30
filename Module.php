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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 */

namespace Aurora\Modules\Twilio;

/**
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
	public function init() 
	{
		parent::init();
		
		$this->extendObject('CTenant', array(
				'TwilioAllow'				=> array('bool', !!$this->GetConfig('Allow'), false), //, 
				'TwilioAllowConfiguration'	=> array('bool', false),
				'TwilioPhoneNumber'			=> array('string', (string) $this->GetConfig('PhoneNumber'), false),
				'TwilioAccountSID'			=> array('string', (string) $this->GetConfig('AccountSID'), false),
				'TwilioAuthToken'			=> array('string', (string) $this->GetConfig('AuthToken'), false),
				'TwilioAppSID'				=> array('string', (string) $this->GetConfig('AppSID'), false)
			)
		);
		
		$this->extendObject('CUser', array(
				'TwilioEnable'						=> array('bool', true), //'twilio_enable'),
				'TwilioNumber'						=> array('string', ''), //'twilio_number'),
				'TwilioDefaultNumber'				=> array('bool', false), //'twilio_default_number'),
			)
		);
		
		$this->AddEntry('twilio', 'getTwiML');
	}

	public function getTwiML()
	{
		$oApiCapability = \Aurora\System\Api::GetSystemManager('capability');
//		$oApiUsers = \Aurora\System\Api::GetSystemManager('users');
//		$oApiTenants = \Aurora\System\Api::GetSystemManager('tenants');

		$sTenantId = \Aurora\System\Application::GetPathItemByIndex(1);
		
		$oTenant = null;
		if ($oApiTenants)
		{
			$oTenant = $sTenantId ? $oApiTenants->getTenantById($sTenantId) : $oApiTenants->getDefaultGlobalTenant();
		}

		$sTwilioPhoneNumber = $oTenant->TwilioPhoneNumber;

		$sDigits = $this->oHttp->GetRequest('Digits');
		//$sFrom = str_replace('client:', '', $oHttp->GetRequest('From'));
		$sFrom = $this->oHttp->GetRequest('From');
		$sTo = $this->oHttp->GetRequest('PhoneNumber');

		$aTwilioNumbers = $oApiUsers->getTwilioNumbers($sTenantId);

		@header('Content-type: text/xml');
		$aResult = array('<?xml version="1.0" encoding="UTF-8"?>');
		$aResult[] = '<Response>';

		if ($this->oHttp->GetRequest('CallSid'))
		{
			if ($this->oHttp->GetRequest('AfterlogicCall')) //internal call from webmail first occurrence
			{
				if (\preg_match("/^[\d\+\-\(\) ]+$/", $sTo) && \strlen($sTo) > 0 && \strlen($sTo) < 10) //to internal number
				{
					$aResult[] = '<Dial callerId="'.$sFrom.'"><Client>'.$sTo.'</Client></Dial>';
				}
				else if (\strlen($sTo) > 10) //to external number
				{
					$aResult[] = '<Dial callerId="'.$sFrom.'">'.$sTo.'</Dial>';
				}

				//@setcookie('twilioCall['.$oHttp->GetRequest('CallSid').']', $sTo, time()+60);
				@\setcookie('PhoneNumber', $sTo);
			}
			else //call from other systems or internal call second occurrence
			{
				if ($oTenant->TwilioAccountSID === $this->oHttp->GetRequest('AccountSid') && $oTenant->TwilioAppSID === $this->oHttp->GetRequest('ApplicationSid')) //internal call second occurrence
				{
					/*$sTo = isset($_COOKIE['twilioCall'][$oHttp->GetRequest('CallSid')]) ? $_COOKIE['twilioCall'][$oHttp->GetRequest('CallSid')] : '';
					@setcookie ('twilioCall['.$oHttp->GetRequest('CallSid').']', '', time() - 1);*/
					if (\strlen($sTo) > 0 && \strlen($sTo) < 10) //to internal number
					{
						$aResult[] = '<Dial callerId="'.$sFrom.'"><Client>'.$sTo.'</Client></Dial>';
					}
					else if (\strlen($sTo) > 10) //to external number
					{
						$aResult[] = '<Dial callerId="'.$sTwilioPhoneNumber.'">'.$sTo.'</Dial>'; //in there caller id must be full with country code number!
					}
				}
				else //call from other systems
				{
					if ($sDigits) //second occurrence
					{
						$aResult[] = '<Dial callerId="'.$sDigits.'"><Client>'.$sDigits.'</Client></Dial>';
					}
					else //first occurrence
					{
						$aResult[] = '<Gather timeout="5" numDigits="4">';
						$aResult[] = '<Say>Please enter the extension number or stay on the line</Say>';
						$aResult[] = '</Gather>';
						//$aResult[] = '<Say>You will be connected with an operator</Say>';
						$aResult[] = self::_getDialToDefault($oApiUsers->getTwilioNumbers($sTenantId));
					}
				}
			}
		}
		else
		{
			$aResult[] = '<Say>This functionality doesn\'t allowed</Say>';
		}

		$aResult[] = '</Response>';

		\Aurora\System\Api::LogObject('twilio_xml_start');
		\Aurora\System\Api::LogObject($_REQUEST);
		\Aurora\System\Api::LogObject($aTwilioNumbers);
		\Aurora\System\Api::LogObject($aResult);
		\Aurora\System\Api::LogObject('twilio_From-'.$sFrom);
		\Aurora\System\Api::LogObject('twilio_TwilioPhoneNumber-'.$oTenant->TwilioPhoneNumber);
		\Aurora\System\Api::LogObject('twilio_TwilioAllow-'.$oTenant->TwilioAllow);
		\Aurora\System\Api::LogObject('twilio_xml_end');

		//return implode("\r\n", $aResult);
		return \implode('', $aResult);
	}

	public function getCallSimpleStatus($sStatus, $sUserDirection)
	{
		$sSimpleStatus = '';

		if (($sStatus === 'busy' || $sStatus === 'completed') && $sUserDirection === 'incoming')
		{
			$sSimpleStatus = 'incoming';
		}
		else if (($sStatus === 'busy' || $sStatus === 'completed' || $sStatus === 'failed' || $sStatus === 'no-answer') && $sUserDirection === 'outgoing')
		{
			$sSimpleStatus = 'outgoing';
		}
		else if ($sStatus === 'no-answer' && $sUserDirection === 'incoming')
		{
			$sSimpleStatus = 'missed';
		}

		return $sSimpleStatus;
	}

	private static function _getDialToDefault($aPhones)
	{
		// the number of <Client> may not exceed 10
		$sDial = '<Dial>';
		$sDial .= '<Client>default</Client>';
		foreach ($aPhones as $iKey => $sValue) 
		{
			if($aPhones[$iKey])
			{
				$sDial .= '<Client>'.$iKey.'</Client>';
			}
		}
		$sDial .= '</Dial>';

		return $sDial;
	}	
	
	/**
	 * @return array
	 */
	public function GetToken()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		return false; // TODO:
		$oAccount = $this->getAccountFromParam();

//		$oApiTenants = \Aurora\System\Api::GetSystemManager('tenants');
		$oTenant = (0 < $oAccount->IdTenant) ? $oApiTenants->getTenantById($oAccount->IdTenant) : $oApiTenants->getDefaultGlobalTenant();
		
		$mToken = false;
		if ($oTenant && $this->oApiCapabilityManager->isTwilioSupported($oAccount) && $oTenant->isTwilioSupported() && $oTenant->TwilioAllow && $oAccount->User->TwilioEnable && file_exists(AURORA_APP_ROOT_PATH.'libraries/Services/Twilio.php'))
		{
			try
			{
				// Twilio API credentials
				$sAccountSid = $oTenant->TwilioAccountSID;
				$sAuthToken = $oTenant->TwilioAuthToken;
				// Twilio Application Sid
				$sAppSid = $oTenant->TwilioAppSID;

				$sTwilioPhoneNumber = $oTenant->TwilioPhoneNumber;
				$bUserTwilioEnable = $oAccount->User->TwilioEnable;
				$sUserPhoneNumber = $oAccount->User->TwilioNumber;
				$bUserDefaultNumber = $oAccount->User->TwilioDefaultNumber;

				$oCapability = new \Services_Twilio_Capability($sAccountSid, $sAuthToken);
				$oCapability->allowClientOutgoing($sAppSid);

				\Aurora\System\Api::Log('twilio_debug');
				\Aurora\System\Api::Log('twilio_account_sid-' . $sAccountSid);
				\Aurora\System\Api::Log('twilio_auth_token-' . $sAuthToken);
				\Aurora\System\Api::Log('twilio_app_sid-' . $sAppSid);
				\Aurora\System\Api::Log('twilio_enable-' . $bUserTwilioEnable ? 'true' : 'false');
				\Aurora\System\Api::Log('twilio_user_default_number-' . ($bUserDefaultNumber ? 'true' : 'false'));
				\Aurora\System\Api::Log('twilio_number-' . $sTwilioPhoneNumber);
				\Aurora\System\Api::Log('twilio_user_number-' . $sUserPhoneNumber);
				\Aurora\System\Api::Log('twilio_debug_end');

				//$oCapability->allowClientIncoming('TwilioAftId_'.$oAccount->IdTenant.'_'.$oAccount->User->TwilioNumber);

				if ($bUserTwilioEnable)
				{
					if ($bUserDefaultNumber)
					{
						$oCapability->allowClientIncoming(strlen($sUserPhoneNumber) > 0 ? $sUserPhoneNumber : 'default');
					}
					else if (\strlen($sUserPhoneNumber) > 0)
					{
						$oCapability->allowClientIncoming($sUserPhoneNumber);
					}
				}

				$mToken = $oCapability->generateToken(86400000); //Token lifetime set to 24hr (default 1hr)
			}
			catch (\Exception $oE)
			{
				\Aurora\System\Api::LogException($oE);
			}
		}
		else
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::VoiceNotAllowed);
		}

		return $mToken;
	}	
	
	/**
	 * @return array
	 */
	public function GetLogs()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		return array(); // TODO:
		
		$oAccount = $this->getAccountFromParam();

		$bTwilioEnable = $oAccount->User->TwilioEnable;

//		$oApiTenants = \Aurora\System\Api::GetSystemManager('tenants');
		$oTenant = (0 < $oAccount->IdTenant) ? $oApiTenants->getTenantById($oAccount->IdTenant) :
			$this->oApiTenants->getDefaultGlobalTenant();

		if ($oTenant && $this->oApiCapabilityManager->isTwilioSupported($oAccount) && $oTenant->isTwilioSupported())
		{
			try
			{
				include AURORA_APP_ROOT_PATH.'libraries/Services/Twilio.php';

				$sStatus = (string) $this->getParamValue('Status', '');
				$sStartTime = (string) $this->getParamValue('StartTime', '');

				$sAccountSid = $oTenant->TwilioAccountSID;
				$sAuthToken = $oTenant->TwilioAuthToken;
				$sAppSid = $oTenant->TwilioAppSID;

				$sTwilioPhoneNumber = $oTenant->TwilioPhoneNumber;
				$sUserPhoneNumber = $oAccount->User->TwilioNumber;
				$aResult = array();
				$aNumbers = array();
				$aNames = array();

				$client = new \Services_Twilio($sAccountSid, $sAuthToken);

				//$sUserPhoneNumber = '7333';
				if ($sUserPhoneNumber) {
					foreach ($client->account->calls->getIterator(0, 50, array
					(
						"Status" => $sStatus,
						"StartTime>" => $sStartTime,
						"From" => "client:".$sUserPhoneNumber,
					)) as $call)
					{
						//$aResult[$call->status]["outgoing"][] = array
						$aResult[] = array
						(
							"Status" => $call->status,
							"To" => $call->to,
							"ToFormatted" => $call->to_formatted,
							"From" => $call->from,
							"FromFormatted" => $call->from_formatted,
							"StartTime" => $call->start_time,
							"EndTime" => $call->end_time,
							"Duration" => $call->duration,
							"Price" => $call->price,
							"PriceUnit" => $call->price_unit,
							"Direction" => $call->direction,
							"UserDirection" => "outgoing",
							"UserStatus" => $this->oApiTwilio->getCallSimpleStatus($call->status, "outgoing"),
							"UserPhone" => $sUserPhoneNumber,
							"UserName" => '',
							"UserDisplayName" => '',
							"UserEmail" => ''
						);

						$aNumbers[] = $call->to_formatted;
					}

					foreach ($client->account->calls->getIterator(0, 50, array
					(
						"Status" => $sStatus,
						"StartTime>" => $sStartTime,
						"To" => "client:".$sUserPhoneNumber
					)) as $call)
					{
						//$aResult[$call->status]["incoming"][] = array
						$aResult[] = array
						(
							"Status" => $call->status,
							"To" => $call->to,
							"ToFormatted" => $call->to_formatted,
							"From" => $call->from,
							"FromFormatted" => $call->from_formatted,
							"StartTime" => $call->start_time,
							"EndTime" => $call->end_time,
							"Duration" => $call->duration,
							"Price" => $call->price,
							"PriceUnit" => $call->price_unit,
							"Direction" => $call->direction,
							"UserDirection" => "incoming",
							"UserStatus" => $this->oApiTwilio->getCallSimpleStatus($call->status, "incoming"),
							"UserPhone" => $sUserPhoneNumber,
							"UserName" => '',
							"UserDisplayName" => '',
							"UserEmail" => ''

						);

						$aNumbers[] = $call->from_formatted;
					}

					$oApiVoiceManager = \Aurora\System\Api::Manager('voice');

					if ($aResult && $oApiVoiceManager) {

						$aNames = $oApiVoiceManager->getNamesByCallersNumbers($oAccount, $aNumbers);

						foreach ($aResult as &$aCall) {

							if ($aCall['UserDirection'] === 'outgoing')
							{
								$aCall['UserDisplayName'] = isset($aNames[$aCall['ToFormatted']]) ? $aNames[$aCall['ToFormatted']] : '';
							}
							else if ($aCall['UserDirection'] === 'incoming')
							{
								$aCall['UserDisplayName'] = isset($aNames[$aCall['FromFormatted']]) ? $aNames[$aCall['FromFormatted']] : '';
							}
						}
					}
				}
			}
			catch (\Exception $oE)
			{
				\Aurora\System\Api::LogException($oE);
			}
		}
		else
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::VoiceNotAllowed);
		}

		return $aResult;
	}	
}
