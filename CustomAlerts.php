<?php

/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html Gpl v3 or later
 * @version $Id$
 *
 */

namespace Piwik\Plugins\CustomAlerts;

use Piwik\Piwik;
use Piwik\Db;
use Piwik\Menu\MenuTop;
use Piwik\ScheduledTask;
use Piwik\ScheduledTime;
use Piwik\Plugins\SitesManager\API as SitesManagerApi;

/**
 *
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
            'API.Request.dispatch'              => 'checkApiPermission',
            'Request.dispatch'                  => 'checkControllerPermission',
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
            'UsersManager.deleteUser'           => 'deleteAlertsForLogin',
            'SitesManager.deleteSite.end'       => 'deleteAlertsForSite'
		);
	}

    public function checkApiPermission(&$parameters, $pluginName, $methodName)
    {
        if ($pluginName == 'CustomAlerts') {
            $this->checkPermission();
        }
    }

    public function checkControllerPermission($module, $action)
    {
        if ($module != 'CustomAlerts') {
            return;
        }

        if ($action == 'formatAlerts') {
            throw new \Exception('This action does not exist');
        }

        $this->checkPermission();
    }

    private function checkPermission()
    {
        Piwik::checkUserIsNotAnonymous();
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

        MenuTop::addEntry($title, array('module' => 'CustomAlerts', 'action' => 'index'), !Piwik::isUserIsAnonymous(), 9);
    }

    public function getScheduledTasks(&$tasks)
    {
        $this->scheduleTask($tasks, 'runAlertsDaily', 'day');
        $this->scheduleTask($tasks, 'runAlertsWeekly', 'week');
        $this->scheduleTask($tasks, 'runAlertsMonthly', 'month');
    }

    public function deleteAlertsForLogin($userLogin)
    {
        $model  = $this->getModel();
        $alerts = $this->getAllAlerts();

        foreach ($alerts as $alert) {
            if ($alert['login'] == $userLogin) {
                $model->deleteAlert($alert['idalert']);
            }
        }
    }

    public function deleteAlertsForSite($idSite)
    {
        $model  = $this->getModel();
        $model->deleteTriggeredAlertsForSite($idSite);

        $alerts = $this->getAllAlerts();

        foreach ($alerts as $alert) {
            $key = array_search($idSite, $alert['id_sites']);

            if (false !== $key) {
                unset($alert['id_sites'][$key]);
                $model->setSiteIds($alert['idalert'], array_values($alert['id_sites']));

                // TODO also delete logs
            }
        }
    }

    public function removePhoneNumberFromAllAlerts($phoneNumber)
    {
        $model  = $this->getModel();
        $alerts = $this->getAllAlerts();

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
                    $alert['id_sites'],
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
        $this->runAlerts('day', $idSite);
    }

    public function runAlertsWeekly($idSite)
    {
        $this->runAlerts('week', $idSite);
    }

    public function runAlertsMonthly($idSite)
    {
        $this->runAlerts('month', $idSite);
    }

    private function runAlerts($period, $idSite)
    {
        $processor = new Processor();
        $processor->processAlerts($period, (int) $idSite);
        $notifier  = new Notifier();
        $notifier->sendNewAlerts($period, (int) $idSite);
    }

    private function scheduleTask(&$tasks, $methodName, $period)
    {
        $siteIds = $this->getSiteIdsHavingAlerts();

        foreach ($siteIds as $siteId) {
            $tasks[] = new ScheduledTask (
                $this,
                $methodName,
                $siteId,
                ScheduledTime::getScheduledTimeForSite($siteId, $period)
            );
        }
    }

    public function getSiteIdsHavingAlerts()
    {
        $siteIds = SitesManagerApi::getInstance()->getAllSitesId();

        $model  = new Model();
        $alerts = $model->getAlerts($siteIds);

        $siteIdsHavingAlerts = array();
        foreach ($alerts as $alert) {
            $siteIdsHavingAlerts = array_merge($siteIdsHavingAlerts, $alert['id_sites']);
        }

        return array_values(array_unique($siteIdsHavingAlerts));
    }

    public function getClientSideTranslationKeys(&$translations)
    {
        $translations[] = 'CustomAlerts_InvalidMetricValue';
    }

    private function getModel()
    {
        return new Model();
    }

    /**
     * @return array
     */
    private function getAllAlerts()
    {
        return $this->getModel()->getAllAlerts();
    }
}
