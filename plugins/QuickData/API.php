<?php

namespace Piwik\Plugins\QuickData;

use Piwik\DataTable;
use Piwik\DataTable\Row;

class API extends \Piwik\Plugin\API {

    static private $instance = null;

    const PIWIK_DATABASE_NUMERIC_ARCHIVE_PREFIX = 'piwik_archive_numeric_';

    static public function getInstance()
    {
        if (self::$instance == null)
        {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /*
     *
     * WORKING SQL EXAMPLE
     * (TRUNCATE(100*(( sum-IFNULL(sum_pst, 0) )/IFNULL(sum_pst, 1)),1))
     SELECT idsite, pst.idsite_pst ,name, sum, ( CASE WHEN ISNULL(sum_pst)  THEN 100 ELSE TRUNCATE( 100 * ( ( sum-sum_pst)/sum_pst) ,1) END) as evo, values_range, date_range
FROM (
SELECT `idsite`,`name`,SUM(`value`) as sum , GROUP_CONCAT( `value` ORDER BY `date1` ASC) as values_range , GROUP_CONCAT(`date1` ORDER BY `date1` ASC) as date_range
FROM
( SELECT * FROM `piwik_archive_numeric_2013_04` WHERE (`name` = 'nb_visits' OR `name` = 'Actions_nb_pageviews') AND PERIOD = 1 AND `date1` >= '2013-04-02' AND `date2` <= '2013-05-02' AND `idsite` IN(303,272)
UNION
SELECT * FROM `piwik_archive_numeric_2013_05` WHERE (`name` = 'nb_visits' OR `name` = 'Actions_nb_pageviews') AND PERIOD = 1 AND `date1` >= '2013-04-02' AND `date2` <= '2013-05-02' AND `idsite` IN(303,272) ) as selunion GROUP BY `idsite`,`name` ) as crnt
LEFT JOIN
 (SELECT `idsite` as idsite_pst,`name` as name_pst, SUM(`value`) as sum_pst
FROM
( SELECT * FROM `piwik_archive_numeric_2013_03` WHERE (`name` = 'nb_visits' OR `name` = 'Actions_nb_pageviews') AND PERIOD = 1 AND `date1` >= '2013-03-02' AND `date2` <= '2013-04-01' AND `idsite` IN(303,272)
UNION
SELECT * FROM `piwik_archive_numeric_2013_04` WHERE (`name` = 'nb_visits' OR `name` = 'Actions_nb_pageviews') AND PERIOD = 1 AND `date1` >= '2013-03-02' AND `date2` <= '2013-04-01' AND `idsite` IN(303,272) ) as selunion_pst GROUP BY `idsite`,`name` ) as pst
ON `crnt`.`idsite` = `pst`.`idsite_pst` AND `crnt`.`name` = `pst`.`name_pst` GROUP BY `idsite`,`name`

    *
    *CHECK OF PAST CALCULATION
    *
    SELECT `idsite` as idsite_pst,`name` as name_pst, SUM(`value`) as sum_pst
FROM
( SELECT * FROM `piwik_archive_numeric_2013_03` WHERE (`name` = 'nb_visits' OR `name` = 'Actions_nb_pageviews') AND PERIOD = 1 AND `date1` >= '2013-03-02' AND `date2` <= '2013-04-01' AND `idsite` IN(303,272)
UNION
SELECT * FROM `piwik_archive_numeric_2013_04` WHERE (`name` = 'nb_visits' OR `name` = 'Actions_nb_pageviews') AND PERIOD = 1 AND `date1` >= '2013-03-02' AND `date2` <= '2013-04-01' AND `idsite` IN(303,272) ) as selunion_pst GROUP BY `idsite`,`name`

     *
     * */

    /*
     * @param string - $idSite - piwik site ID's (one or few come separated)
     * @param $period - $period - period of days (previous30, previous15, previous10, previous5) can be whatever number previousXX (XXX*)
     * @param $date - $date - specific date to count from
     *
     * @return array of calculated metrics and evolution values
     *
     * sudo php /home/seotoaster/projects/piwik_repo/console generate:plugin
     *
     */

    public function getEvolution($idSite, $period, $date = null) {
        //Piwik_Date::adjustForTimezone('','');
        date_default_timezone_set(date_default_timezone_get());
        if($date == null) {
            $date = 'now';
        }
        if($idSite != 'all') {
            $idSite = " AND `idsite` IN(".$idSite.")";
        }
        else {
            $idSite = "";
        }
        $endDateRange = date('Y-m-d',strtotime('-1 day', strtotime($date)));
        $startDateRange = date('Y-m-d',strtotime('-'.preg_replace('/[^0-9]/', '', $period).' day', strtotime($endDateRange)));
        //echo $endDateRange.' / '.$startDateRange.'/*';
        $rangeStart = new \DateTime(date('Y-m',strtotime($startDateRange)));
        $rangeEnd = new \DateTime(date('Y-m',strtotime($endDateRange)));
        $rangePeriod = new \DateInterval('P1M');
        $dateRange = new \DatePeriod($rangeStart, $rangePeriod, $rangeEnd);
        //echo $endDateRange .' / ';

        $endPastDateRange = date('Y-m-d',strtotime('-1 day', strtotime($startDateRange)));
        $startPastDateRange = date('Y-m-d',strtotime('-'.preg_replace('/[^0-9]/', '', $period).' day', strtotime($endPastDateRange)));
        //echo $endPastDateRange .' / '.$startPastDateRange;
        $rangePastStart = new \DateTime(date('Y-m',strtotime($startPastDateRange)));
        $rangePastEnd = new \DateTime(date('Y-m',strtotime($endPastDateRange)));
        $datePastRange = new \DatePeriod($rangePastStart, $rangePeriod, $rangePastEnd);
        $unionArchiveSql = '';
        $unionPastArchiveSql = '';
        foreach($dateRange as $dateItem) {
            $unionArchiveSql .= "SELECT * FROM `".self::PIWIK_DATABASE_NUMERIC_ARCHIVE_PREFIX.$dateItem->format('Y_m')."` WHERE (`name` = 'nb_visits' OR `name` = 'Actions_nb_pageviews') AND PERIOD = 1  AND `date1` >= '".$startDateRange."' AND `date2` <= '".$endDateRange."' ".$idSite." UNION ";
        }
        $unionArchiveSql .= "SELECT * FROM `".self::PIWIK_DATABASE_NUMERIC_ARCHIVE_PREFIX.date('Y_m',strtotime($endDateRange))."` WHERE (`name` = 'nb_visits' OR `name` = 'Actions_nb_pageviews') AND PERIOD = 1  AND `date1` >= '".$startDateRange."' AND `date2` <= '".$endDateRange."' ".$idSite."";

        foreach($datePastRange as $dateItem) {
            $unionPastArchiveSql .= "SELECT * FROM `".self::PIWIK_DATABASE_NUMERIC_ARCHIVE_PREFIX.$dateItem->format('Y_m')."` WHERE (`name` = 'nb_visits' OR `name` = 'Actions_nb_pageviews') AND PERIOD = 1  AND `date1` >= '".$startPastDateRange."' AND `date2` <= '".$endPastDateRange."' ".$idSite." UNION ";
        }
        $unionPastArchiveSql .= "SELECT * FROM `".self::PIWIK_DATABASE_NUMERIC_ARCHIVE_PREFIX.date('Y_m',strtotime($endPastDateRange))."` WHERE (`name` = 'nb_visits' OR `name` = 'Actions_nb_pageviews') AND PERIOD = 1  AND `date1` >= '".$startPastDateRange."' AND `date2` <= '".$endPastDateRange."' ".$idSite."";

        //return date('Y-m-d',strtotime('-'.preg_replace('/[^0-9]/', '', $period).' day'));
        try{
        $zendDb = new \Zend_Db_Table();
        $evolutionResult = $zendDb->getAdapter()->fetchAll("SELECT `idsite` ,`name`, sum, ( CASE WHEN ISNULL(sum_pst) THEN 100 ELSE TRUNCATE( 100 * ( ( sum-sum_pst)/sum_pst) ,1) END) as evo, values_range, date_range
            FROM (
             SELECT `idsite`,`name`,SUM(`value`) as sum , GROUP_CONCAT( `value` ORDER BY `date1` ASC) as values_range , GROUP_CONCAT(`date1` ORDER BY `date1` ASC) as date_range
              FROM (
            ".$unionArchiveSql."
            ) as selunion GROUP BY `idsite`,`name`
            ) as crnt
             LEFT JOIN

            (SELECT `idsite` as idsite_pst,`name` as name_pst, SUM(`value`) as sum_pst
             FROM (
            ".$unionPastArchiveSql."
            ) as selunion_pst GROUP BY `idsite`,`name`
            ) as pst

            ON `crnt`.`idsite` = `pst`.`idsite_pst` AND `crnt`.`name` = `pst`.`name_pst` GROUP BY `idsite`,`name`");
        }catch (Exception $e) {return $e->getMessage();}
        $evolutionResult['dateRange']['start'] = $startDateRange;
        $evolutionResult['dateRange']['end'] = $endDateRange;
        return $evolutionResult;
    }


}