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

use Piwik\Period;
use Piwik\Db;
use Piwik\Piwik;
use Piwik\Plugins\API\ProcessedReport;
use Piwik\Site;
use Piwik\Translate;
use Piwik\Plugins\MobileMessaging\API as APIMobileMessaging;
use Piwik\Plugins\API\API as MetadataApi;
use Exception;

/**
 *
 * @package Piwik_Alerts
 * @method static \Piwik\Plugins\CustomAlerts\API getInstance()
 */
class API extends \Piwik\Plugin\API
{
    /**
     * Returns a single Alert
     *
     * @param int $idAlert
     *
     * @throws \Exception
     * @return array
     */
	public function getAlert($idAlert)
	{
        $alert = $this->getModel()->getAlert($idAlert);

        if (empty($alert)) {
            throw new Exception(Piwik::translate('CustomAlerts_AlertDoesNotExist', $idAlert));
        }

        $this->checkUserHasPermissionForAlert($alert);

        return $alert;
    }

    /**
     * Returns the Alerts that are defined on the idSites given.
     * Each alert will be only returned if the current user is the superUser or if the alert belongs to
     * the current user.
     *
     * @param array $idSites
     * @return array
     */
	public function getAlerts($idSites)
	{
        if (empty($idSites)) {
            return array();
        }

        $idSites = Site::getIdSitesFromIdSitesString($idSites);
        Piwik::checkUserHasViewAccess($idSites);

        $alerts = $this->getModel()->getAlerts($idSites);

        foreach ($alerts as $index => $alert) {
            try {
                $this->checkUserHasPermissionForAlert($alert);
            } catch (Exception $e) {
                unset($alerts[$index]);
            }
        }

        return array_values($alerts);
	}

    /**
     * Creates an Alert for given website(s).
     *
     * @param string $name
     * @param mixed $idSites
     * @param string $period
     * @param bool $emailMe
     * @param array $additionalEmails
     * @param array $phoneNumbers
     * @param string $metric (nb_uniq_visits, sum_visit_length, ..)
     * @param string $metricCondition
     * @param float $metricValue
     * @param string $report
     * @param int $comparedTo
     * @param bool|string $reportCondition
     * @param bool|string $reportValue
     * @internal param bool $enableEmail
     * @return int ID of new Alert
     */
	public function addAlert($name, $idSites, $period, $emailMe, $additionalEmails, $phoneNumbers, $metric, $metricCondition, $metricValue, $comparedTo, $report, $reportCondition = false, $reportValue = false)
	{
        $idSites = Site::getIdSitesFromIdSitesString($idSites);

        Piwik::checkUserHasViewAccess($idSites);

        $name = $this->checkName($name);
        $this->checkPeriod($period);
        $this->checkComparedTo($period, $comparedTo);
        $this->checkMetricCondition($metricCondition);
        $this->checkReportCondition($reportCondition);

        foreach ($idSites as $idSite) {
            $this->checkApiMethodAndMetric($idSite, $report, $metric);
        }

        $additionalEmails = $this->checkAdditionalEmails($additionalEmails);
        $phoneNumbers     = $this->checkPhoneNumbers($phoneNumbers);

        $emailMe = $emailMe ? 1 : 0;
        $login = Piwik::getCurrentUserLogin();

        return $this->getModel()->createAlert($name, $idSites, $login, $period, $emailMe, $additionalEmails, $phoneNumbers, $metric, $metricCondition, $metricValue, $comparedTo, $report, $reportCondition, $reportValue);
	}

    /**
     * Edits an Alert for given website(s).
     *
     * @param $idAlert
     * @param string $name Name of Alert
     * @param mixed $idSites Single int or array of ints of idSites.
     * @param string $period Period the alert is defined on.
     * @param bool $emailMe
     * @param array $additionalEmails
     * @param array $phoneNumbers
     * @param string $metric (nb_uniq_visits, sum_visit_length, ..)
     * @param string $metricCondition
     * @param float $metricValue
     * @param string $report
     * @param int $comparedTo
     * @param bool|string $reportCondition
     * @param bool|string $reportValue
     *
     * @internal param bool $enableEmail
     * @return boolean
     */
	public function editAlert($idAlert, $name, $idSites, $period, $emailMe, $additionalEmails, $phoneNumbers, $metric, $metricCondition, $metricValue, $comparedTo, $report, $reportCondition = false, $reportValue = false)
	{
        // make sure alert exists and user has permission to read
        $this->getAlert($idAlert);

        $idSites = Site::getIdSitesFromIdSitesString($idSites);
        Piwik::checkUserHasViewAccess($idSites);

        $name = $this->checkName($name);
        $this->checkPeriod($period);
        $this->checkComparedTo($period, $comparedTo);
        $this->checkMetricCondition($metricCondition);
        $this->checkReportCondition($reportCondition);

        foreach ($idSites as $idSite) {
            $this->checkApiMethodAndMetric($idSite, $report, $metric);
        }

        $additionalEmails = $this->checkAdditionalEmails($additionalEmails);
        $phoneNumbers     = $this->checkPhoneNumbers($phoneNumbers);
        $emailMe = $emailMe ? 1 : 0;

        return $this->getModel()->updateAlert($idAlert, $name, $idSites, $period, $emailMe, $additionalEmails, $phoneNumbers, $metric, $metricCondition, $metricValue, $comparedTo, $report, $reportCondition, $reportValue);
	}

    /**
     * Delete alert by id.
     *
     * @param int $idAlert
     * @throws \Exception
     */
	public function deleteAlert($idAlert)
	{
        // make sure alert exists and user has permission to read
        $this->getAlert($idAlert);

        $this->getModel()->deleteAlert($idAlert);
	}

    /**
     * Get all alerts
     *
     * @param string $period
     * @return array
     * @throws \Exception
     */
	public function getAllAlertsForPeriod($period)
	{
        Piwik::checkUserIsSuperUser();

        if (!$this->isValidPeriod($period)) {
            throw new Exception(Piwik::translate('CustomAlerts_InvalidPeriod'));
        }

        return $this->getModel()->getAllAlertsForPeriod($period);
	}

    /**
     * Get triggered alerts.
     *
     * @param int $idAlert
     * @param int $idSite
     * @param string|int $valueNew
     * @param string|int $valueOld
     * @throws \Exception
     */
    public function triggerAlert($idAlert, $idSite, $valueNew, $valueOld)
    {
        // make sure alert exists and user has permission to read
        $this->getAlert($idAlert);

        $this->getModel()->triggerAlert($idAlert, $idSite, $valueNew, $valueOld);
    }

    /**
     * Get triggered alerts.
     *
     * @param string $period
     * @param string $date
     * @param string $login
     * @return array
     */
	public function getTriggeredAlerts($period, $date, $login)
	{
        Piwik::checkUserIsSuperUserOrTheUser($login);

        $this->checkPeriod($period);

        return $this->getModel()->getTriggeredAlerts($period, $date, $login);
	}

    private function getModel()
    {
        return new Model();
    }

    private function checkAdditionalEmails($additionalEmails)
    {
        foreach ($additionalEmails as &$email) {

            $email = trim($email);
            if (empty($email)) {
                $email = false;
            } elseif (!Piwik::isValidEmailString($email)) {
                throw new \Exception(Piwik::translate('UsersManager_ExceptionInvalidEmail') . ' (' . $email . ')');
            }

        }

        return array_filter($additionalEmails);
    }

    private function checkPhoneNumbers($phoneNumbers)
    {
        $availablePhoneNumbers = APIMobileMessaging::getInstance()->getActivatedPhoneNumbers();

        foreach ($phoneNumbers as $key => &$phoneNumber) {

            $phoneNumber = trim($phoneNumber);

            if (!in_array($phoneNumber, $availablePhoneNumbers)) {
                unset($phoneNumbers[$key]);
            }
        }

        return array_values($phoneNumbers);
    }

    private function checkPeriod($period)
    {
        if (!$this->isValidPeriod($period)) {
            throw new Exception(Piwik::translate('CustomAlerts_InvalidPeriod'));
        }
    }

    private function isValidPeriod($period)
    {
        return in_array($period, array('day', 'week', 'month', 'year'));
    }

    private function checkName($name)
    {
        if (empty($name)) {
            throw new Exception(Piwik::translate("General_PleaseSpecifyValue", "name"));
        }

        return urldecode($name);
    }

    private function checkMetricCondition($condition)
    {
        if (empty($condition)) {
            throw new Exception(Piwik::translate('CustomAlerts_InvalidMetricCondition'));
        }

        if (!Processor::isValidMetricCondition($condition)) {
            throw new Exception(Piwik::translate('CustomAlerts_InvalidMetricCondition'));
        }
    }

    private function checkReportCondition($condition)
    {
        if (!empty($condition) && !Processor::isValidGroupCondition($condition)) {
            throw new Exception(Piwik::translate('CustomAlerts_InvalidReportCondition'));
        }
    }

    private function checkComparedTo($period, $comparedTo)
    {
        if (!Processor::isValidComparableDate($period, $comparedTo)) {
            throw new Exception(Piwik::translate('CustomAlerts_InvalidComparableDate'));
        }
    }

    /**
     * Checks whether a report + metric exists for
     * the given idSites and if the a dimension is
     * given (requires report_condition, report_matched)
     *
     * @param int $idSite
     * @param string $apiMethod for example MultiSites.getAll
     * @param string $metric
     * @throws \Exception
     * @return boolean
     */
    private function checkApiMethodAndMetric($idSite, $apiMethod, $metric)
    {
        if (empty($apiMethod) || false === strpos($apiMethod, '.')) {
            throw new Exception(Piwik::translate('CustomAlerts_InvalidReport'));
        }

        list($module, $action) = explode(".", $apiMethod);

        $processedReport = new ProcessedReport();

        if (!$processedReport->isValidReportForSite($idSite, $module, $action)) {
            throw new Exception(Piwik::translate('CustomAlerts_InvalidReport'));
        }

        if (!$processedReport->isValidMetricForReport($metric, $idSite, $module, $action)) {
            throw new Exception(Piwik::translate('CustomAlerts_InvalidMetric'));
        }
    }

    /**
     * @param $alert
     * @throws \Exception
     */
    private function checkUserHasPermissionForAlert($alert)
    {
        if (!Piwik::isUserIsSuperUserOrTheUser($alert['login'])) {
            throw new Exception(Piwik::translate('CustomAlerts_AccessException', $alert['idalert']));
        }
    }
}
