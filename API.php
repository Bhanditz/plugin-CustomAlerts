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

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Site;
use Piwik\Translate;
use Piwik\Plugins\MobileMessaging\API as APIMobileMessaging;
use Exception;

/**
 *
 * @package Piwik_Alerts
 * @method static \Piwik\Plugins\CustomAlerts\API getInstance()
 */
class API extends \Piwik\Plugin\API
{
    private $validator;

    protected function __construct()
    {
        parent::__construct();

        $this->validator = new Validator();
    }

    /**
     * Returns a single alert.
     *
     * @param int $idAlert
     *
     * @throws \Exception In case alert does not exist or user has no permission to access alert.
     *
     * @return array
     */
	public function getAlert($idAlert)
	{
        $alert = $this->getModel()->getAlert($idAlert);

        if (empty($alert)) {
            throw new Exception(Piwik::translate('CustomAlerts_AlertDoesNotExist', $idAlert));
        }

        $this->validator->checkUserHasPermissionForAlert($alert);

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
                $this->validator->checkUserHasPermissionForAlert($alert);
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
        $idSites          = Site::getIdSitesFromIdSitesString($idSites);
        $additionalEmails = $this->filterAdditionalEmails($additionalEmails);
        $phoneNumbers     = $this->filterPhoneNumbers($phoneNumbers);

        $this->checkAlert($idSites, $name, $period, $additionalEmails, $metricCondition, $metric, $comparedTo, $reportCondition, $report);

        $name  = Common::unsanitizeInputValue($name);
        $login = Piwik::getCurrentUserLogin();

        if (empty($reportCondition) || empty($reportCondition)) {
            $reportCondition = null;
            $reportValue     = null;
        }

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

        $idSites          = Site::getIdSitesFromIdSitesString($idSites);
        $additionalEmails = $this->filterAdditionalEmails($additionalEmails);
        $phoneNumbers     = $this->filterPhoneNumbers($phoneNumbers);

        $this->checkAlert($idSites, $name, $period, $additionalEmails, $metricCondition, $metric, $comparedTo, $reportCondition, $report);

        $name = Common::unsanitizeInputValue($name);

        if (empty($reportCondition) || empty($reportCondition)) {
            $reportCondition = null;
            $reportValue     = null;
        }

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

        $this->validator->checkPeriod($period);

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

        $this->validator->checkPeriod($period);

        return $this->getModel()->getTriggeredAlerts($period, $date, $login);
	}

    private function getModel()
    {
        return new Model();
    }

    private function filterAdditionalEmails($additionalEmails)
    {
        if (empty($additionalEmails)) {
            return array();
        }

        foreach ($additionalEmails as &$email) {

            $email = trim($email);
            if (empty($email)) {
                $email = false;
            }
        }

        return array_filter($additionalEmails);
    }

    private function filterPhoneNumbers($phoneNumbers)
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

    private function checkAlert($idSites, $name, $period, $additionalEmails, $metricCondition, $metricValue, $comparedTo, $reportCondition, $report)
    {
        Piwik::checkUserHasViewAccess($idSites);

        $this->validator->checkName($name);
        $this->validator->checkPeriod($period);
        $this->validator->checkComparedTo($period, $comparedTo);
        $this->validator->checkMetricCondition($metricCondition);
        $this->validator->checkReportCondition($reportCondition);

        foreach ($idSites as $idSite) {
            $this->validator->checkApiMethodAndMetric($idSite, $report, $metricValue);
        }

        $this->validator->checkAdditionalEmails($additionalEmails);
    }

}
