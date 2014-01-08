<?php

/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html Gpl v3 or later
 * @version $Id$
 *
 * @category Piwik_Plugins
 * @package Piwik_Alerts
 */

namespace Piwik\Plugins\CustomAlerts;

use Piwik\Date;
use Piwik\Piwik;
use Piwik\Db;
use Piwik\Menu\MenuTop;
use Piwik\ScheduledTask;
use Piwik\ScheduledTime;
use Piwik\Site;
use Piwik\Plugins\SitesManager\API as SitesManagerApi;

/**
 *
 * @package Piwik_Alerts
 */
class CustomAlerts extends \Piwik\Plugin
{
    
	public function getListHooksRegistered()
	{
		return array(
		    'Menu.Top.addItems'                 => 'addTopMenu',
		    'TaskScheduler.getScheduledTasks'   => 'getScheduledTasks',
		    'MobileMessaging.deletePhoneNumber' => 'removePhoneNumberFromAllAlerts',
		    'AssetManager.getJavaScriptFiles'   => 'getJavaScriptFiles',
		    'AssetManager.getStylesheetFiles'   => 'getStylesheetFiles',
		);
	}

    public function install()
    {
        Model::install();
    }

    public function uninstall()
    {
        Model::uninstall();
    }

	public function getJavaScriptFiles(&$jsFiles)
	{
		$jsFiles[] = "plugins/CustomAlerts/javascripts/alerts.js";
	}

	public function getStylesheetFiles(&$cssFiles)
	{
		$cssFiles[] = "plugins/CustomAlerts/stylesheets/alerts.less";
	}

    public function addTopMenu()
    {
        $title = Piwik::translate('CustomAlerts_Alerts');

        MenuTop::addEntry($title, array('module' => 'CustomAlerts', 'action' => 'index'), true, 9);
    }

    public function getScheduledTasks(&$tasks)
    {
        $this->scheduleTask($tasks, 'runAlertsDaily', 'day');
        $this->scheduleTask($tasks, 'runAlertsWeekly', 'week');
        $this->scheduleTask($tasks, 'runAlertsMonthly', 'month');
    }

    public function removePhoneNumberFromAllAlerts($phoneNumber)
    {
        $model  = new Model();
        $alerts = $model->getAllAlerts();

        foreach ($alerts as $alert) {
            if (empty($alert['phone_numbers']) || !is_array($alert['phone_numbers'])) {
                continue;
            }

            $key = array_search($phoneNumber, $alert['phone_numbers'], true);

            if (false !== $key) {
                unset($alert['phone_numbers'][$key]);
                $model->updateAlert(
                    $alert['idalert'],
                    $alert['name'],
                    $alert['idSites'],
                    $alert['period'],
                    $alert['email_me'],
                    $alert['additional_emails'],
                    array_values($alert['phone_numbers']),
                    $alert['metric'],
                    $alert['metric_condition'],
                    $alert['metric_matched'],
                    $alert['compared_to'],
                    $alert['report'],
                    $alert['report_condition'],
                    $alert['report_matched']
                );
            }
        }
    }

    public function runAlertsDaily($idSite)
    {
        $this->runAlerts('day', (int) $idSite);
    }

    public function runAlertsWeekly($idSite)
    {
        $this->runAlerts('week', (int) $idSite);
    }

    public function runAlertsMonthly($idSite)
    {
        $this->runAlerts('month', (int) $idSite);
    }

    private function runAlerts($period, $idSite)
    {
        $processor = new Processor();
        $processor->processAlerts($period, $idSite);
        $notifier  = new Notifier();
        $notifier->sendNewAlerts($period, $idSite);
    }

    private function scheduleTask(&$tasks, $methodName, $period)
    {
        $siteIds = SitesManagerApi::getInstance()->getAllSitesId();

        foreach ($siteIds as $siteId) {
            $tasks[] = new ScheduledTask (
                $this,
                $methodName,
                $siteId,
                ScheduledTime::getScheduledTimeForSite($siteId, $period)
            );
        }
    }
}