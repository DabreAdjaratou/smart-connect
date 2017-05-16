<?php

namespace Application\Model;

use Zend\Session\Container;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;
use Zend\Db\TableGateway\AbstractTableGateway;
use Zend\Debug\Debug;
use Zend\Db\Sql\Expression;
use \Application\Service\CommonService;
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Countries
 *
 * @author amit
 */
class SampleTable extends AbstractTableGateway {

    protected $table = 'dash_vl_request_form';

    public function __construct(Adapter $adapter) {
        $this->adapter = $adapter;
    }
    
    
    public function fetchQuickStats($params){
        $dbAdapter = $this->adapter;$sql = new Sql($dbAdapter);
        $common = new CommonService();
//        $query = "SELECT count(*) as 'Total', 
//		SUM(CASE 
//            WHEN patient_gender IS NULL OR patient_gender ='' THEN 0
//            ELSE 1
//            END) as GenderMissing, 
//		SUM(CASE 
//            WHEN patient_age_in_years IS NULL OR patient_age_in_years ='' THEN 0
//            ELSE 1
//            END) as AgeMissing,
//        SUM(CASE
//            WHEN (result is NULL OR result ='') AND (sample_collection_date > DATE_SUB(NOW(), INTERVAL 6 MONTH) AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='')) THEN 1
//            ELSE 0
//            END) as ResultWaiting
//           FROM `dash_vl_request_form` as vl";
           
        $globalDb = new \Application\Model\GlobalTable($this->adapter);
        $samplesWaitingFromLastXMonths = $globalDb->getGlobalValue('sample_waiting_month_range');
        
        $query = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(
                                          array("Total Samples" => new Expression('COUNT(*)'),
                                          "Gender Missing" => new Expression("SUM(CASE 
                                                                                    WHEN patient_gender IS NULL OR patient_gender ='' THEN 0
                                                                                    ELSE 1
                                                                                    END)"),
                                          "Age Missing" => new Expression("SUM(CASE 
                                                                                WHEN patient_age_in_years IS NULL OR patient_age_in_years ='' THEN 0
                                                                                ELSE 1
                                                                                END)"),
                                          "Results Awaited (> $samplesWaitingFromLastXMonths months)" => new Expression("SUM(CASE
                                                                                    WHEN (result is NULL OR result ='') AND (sample_collection_date > DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH) AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='')) THEN 1
                                                                                    ELSE 0
                                                                                    END)")
                                          )
                                        );
        if($params['facilityId'] !=''){
            $query = $query->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
        }
        
        $queryStr = $sql->getSqlStringForSqlObject($query);
        
        $result = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $result[0];
        
    }
    
    //start lab dashboard details 
    public function fetchSampleResultDetails($params){
        $quickStats = $this->fetchQuickStats($params);
        $dbAdapter = $this->adapter;$sql = new Sql($dbAdapter);
        $common = new CommonService();
        $timestamp = time();
        $waitingTotal = 0;$receivedTotal = 0;$testedTotal = 0;$rejectedTotal = 0;
        $waitingResult = array();$receivedResult = array();$tResult = array();$rejectedResult = array();
        for ($i = 0 ; $i < 7 ; $i++) {
            $currentDate = date('Y-m-d', $timestamp);
            $displayDate = $common->humanDateFormat(date('y-m-d', $timestamp));
            //get received data
            $receivedQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                               ->columns(array('total' => new Expression('COUNT(*)')))
                                               ->where("vl.result!='' AND vl.result is NOT NULL AND DATE(sample_collection_date) = '".$currentDate."'");
                                               //->where(new \Zend\Db\Sql\Predicate\Expression('DATE(sample_collection_date) = ?', '2017-05-16'));
            if($params['facilityId'] !=''){
                $receivedQuery = $receivedQuery->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
            }
            $cQueryStr = $sql->getSqlStringForSqlObject($receivedQuery);
            //echo $cQueryStr;die;
            $receivedResult[$i] = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if($receivedResult[$i][0]['total']!=0){
                $receivedTotal = $receivedTotal + $receivedResult[$i][0]['total'];
                $receivedResult[$i]['date'] = $displayDate;
                $receivedResult[$i]['receivedDate'] = $displayDate;
                $receivedResult[$i]['receivedTotal'] = $receivedTotal;
            }else{
                unset($receivedResult[$i]);
            }
            //get rejected data
            $rejectedQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                           ->columns(array('total' => new Expression('COUNT(*)')))
                                           ->where("vl.reason_for_sample_rejection !='' AND vl.reason_for_sample_rejection IS NOT NULL AND DATE(sample_collection_date) = '".$currentDate."'");
            if($params['facilityId'] !=''){
               $rejectedQuery = $rejectedQuery->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
            }
            $rQueryStr = $sql->getSqlStringForSqlObject($rejectedQuery);
            $rejectedResult[$i] = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if($rejectedResult[$i][0]['total']!=0){
                $rejectedTotal = $rejectedTotal + $rejectedResult[$i][0]['total'];
                $rejectedResult[$i]['date'] = $displayDate;
                $rejectedResult[$i]['rejectDate'] = $displayDate;
                $rejectedResult[$i]['rejectTotal'] = $rejectedTotal;
            }else{
               unset($rejectedResult[$i]);
            }
            //tested data
            $sQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                    ->where("DATE(sample_tested_datetime)='".$currentDate."'");
            if($params['facilityId'] !=''){
               $sQuery = $sQuery->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
            }
            $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);
            $tResult[$i] = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if($tResult[$i][0]['total']!=0){
                $testedTotal = $testedTotal + $tResult[$i][0]['total'];
                $tResult[$i]['date'] = $displayDate;
                $tResult[$i]['testedDate'] = $displayDate;
                $tResult[$i]['testedTotal'] = $testedTotal;
            }else{
               unset($tResult[$i]);
            }
           $timestamp -= 24 * 3600;
        }
        //waiting query based on global config
            //$i = 0;
            //$globalDb = new \Application\Model\GlobalTable($this->adapter);
            //$mnthRange = $globalDb->getGlobalValue('sample_waiting_month_range');
            //for ($m = 0; $m < $mnthRange; $m++){
            //    $mnth = date('m',strtotime('-'.$m.' month'));
            //    $year = date('Y',strtotime('-'.$m.' month'));
            //    $dFormat = date('M-Y', strtotime('-'.$m.' month'));
            //    $waitingQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
            //                            ->columns(array('total' => new Expression('COUNT(*)')))
            //                            ->where(array("(vl.result='' OR vl.result is NULL)"))
            //                            ->where("Month(sample_collection_date)='".$mnth."' AND Year(sample_collection_date)='".$year."'");
            //    if($params['facilityId'] !=''){
            //       $waitingQuery = $waitingQuery->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
            //    }
            //    $wQueryStr = $sql->getSqlStringForSqlObject($waitingQuery);
            //    $waitingResult[$i] = $dbAdapter->query($wQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            //    if($waitingResult[$i][0]['total']!=0){
            //        $waitingTotal = $waitingTotal + $waitingResult[$i][0]['total'];
            //        $waitingResult[$i]['date'] = $dFormat;
            //        $waitingResult[$i]['waitingDate'] = $dFormat;
            //        $waitingResult[$i]['waitingTotal'] = $waitingTotal;
            //    }else{
            //        unset($waitingResult[$i]);
            //    }
            //    $i++;
            //}
        return array('quickStats'=>$quickStats,'scResult'=>$receivedResult,'stResult'=>$tResult,'srResult'=>$rejectedResult);
    }
    
    //get sample tested result details
    public function fetchSampleTestedResultDetails($params){
        $result = array();
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService();
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])));
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])));
            $start = $month = strtotime($startMonth);
            $end = strtotime($endMonth);
            
            $rsQuery = $sql->select()->from(array('rs'=>'r_sample_type'));
            if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
                $rsQuery = $rsQuery->where('rs.sample_id="'.base64_decode(trim($params['sampleType'])).'"');
            }
            $rsQueryStr = $sql->getSqlStringForSqlObject($rsQuery);
            $sampleTypeResult = $dbAdapter->query($rsQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if($sampleTypeResult){
                $sampleId = array();
                foreach($sampleTypeResult as $samples)
                {
                    $sampleId[] = $samples['sample_id'];
                }
                $j = 0;
                $lessTotal = 0;$greaterTotal = 0;$notTargetTotal = 0;
                while($month <= $end)
                {
                    $mnth = date('m', $month);$year = date('Y', $month);$dFormat = date("M-Y", $month);
                        $lessThanQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                            ->where("Month(sample_collection_date)='".$mnth."' AND Year(sample_collection_date)='".$year."'")
                                            ->where('vl.sample_type IN ("' . implode('", "', $sampleId) . '")');
                        if($params['facilityId'] !=''){
                            $lessThanQuery = $lessThanQuery->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
                        }
                        $lQueryStr = $sql->getSqlStringForSqlObject($lessThanQuery);
                        
                        $greaterResult = $dbAdapter->query($lQueryStr." AND vl.result>1000", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $result['sampleName']['VL (> 1000 cp/ml)'][$j] = $greaterTotal+$greaterResult['total'];
                        
                        $notTargetResult = $dbAdapter->query($lQueryStr." AND 'vl.result'='Target Not Detected'", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $result['sampleName']['VL Not Detected'][$j] = $notTargetTotal+$notTargetResult['total'];
                        
                        $lessResult = $dbAdapter->query($lQueryStr." AND vl.result<1000", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $result['sampleName']['VL (< 1000 cp/ml)'][$j] = $lessTotal+$lessResult['total'];
                        
                    $result['date'][$j] = $dFormat;
                    $month = strtotime("+1 month", $month);
                    $j++;
                }
            }
            return $result;
        }
    }
    //get sample tested result details
    public function fetchSampleTestedResultGenderDetails($params)
    {
        $result = array();
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService();
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])));
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])));
            $start = $month = strtotime($startMonth);
            $end = strtotime($endMonth);
           
                $j = 0;
                $lessTotal = 0;$greaterTotal = 0;$notTargetTotal = 0;
                $gender = array('M','F','');
                while($month <= $end)
                {
                    $mnth = date('m', $month);$year = date('Y', $month);$dFormat = date("M-Y", $month);
                        $lessThanQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)'),'Male' => new Expression('SUM(CASE WHEN patient_gender = "M" OR patient_gender ="m" THEN 1 ELSE 0 END)'),'Female' => new Expression('SUM(CASE WHEN patient_gender = "F" OR patient_gender ="f" THEN 1 ELSE 0 END)'),'Other' => new Expression('SUM(CASE WHEN (patient_gender != "F" AND patient_gender !="f" AND patient_gender != "M" AND patient_gender !="m") THEN 1 ELSE 0 END)')))
                                            ->where("Month(sample_collection_date)='".$mnth."' AND Year(sample_collection_date)='".$year."'");
                                            //->where('vl.sample_type IN ("' . implode('", "', $sampleId) . '")');
                        if($params['facilityId'] !=''){
                            $lessThanQuery = $lessThanQuery->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
                        }
                        $lQueryStr = $sql->getSqlStringForSqlObject($lessThanQuery);
                        
                        //for($g=0;$g<3;$g++){
                        $greaterResult = $dbAdapter->query($lQueryStr." AND vl.result>1000", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $result['M']['VL (> 1000 cp/ml)'][$j] = $greaterTotal+$greaterResult['Male'];
                        $result['F']['VL (> 1000 cp/ml)'][$j] = $greaterTotal+$greaterResult['Female'];
                        $result['Not Specified']['VL (> 1000 cp/ml)'][$j] = $greaterTotal+$greaterResult['Other'];
                        
                        $notTargetResult = $dbAdapter->query($lQueryStr." AND 'vl.result'='Target Not Detected'", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $result['M']['VL Not Detected'][$j] = $notTargetTotal+$notTargetResult['Male'];
                        $result['F']['VL Not Detected'][$j] = $notTargetTotal+$notTargetResult['Female'];
                        $result['Not Specified']['VL Not Detected'][$j] = $notTargetTotal+$notTargetResult['Other'];
                        
                        $lessResult = $dbAdapter->query($lQueryStr." AND vl.result<1000 ", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $result['M']['VL (< 1000 cp/ml)'][$j] = $lessTotal+$lessResult['Male'];
                        $result['F']['VL (< 1000 cp/ml)'][$j] = $lessTotal+$lessResult['Female'];
                        $result['Not Specified']['VL (< 1000 cp/ml)'][$j] = $lessTotal+$lessResult['Other'];
                        //}
                    $result['date'][$j] = $dFormat;
                    $month = strtotime("+1 month", $month);
                    $j++;
                }
            return $result;
        }
    }
    public function fetchSampleTestedResultAgeDetails($params)
    {
        $result = array();
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService();
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])));
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])));
            $start = $month = strtotime($startMonth);
            $end = strtotime($endMonth);
            
                $j = 0;
                $lessTotal = 0;$greaterTotal = 0;$notTargetTotal = 0;
                $age = array('>18','<18');
                while($month <= $end)
                {
                    $mnth = date('m', $month);$year = date('Y', $month);$dFormat = date("M-Y", $month);
                        $lessThanQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)'),'>18' => new Expression('SUM(CASE WHEN patient_age_in_years > 18 THEN 1 ELSE 0 END)'),'<18' => new Expression('SUM(CASE WHEN patient_age_in_years < 18 THEN 1 ELSE 0 END)')))
                                            ->where("Month(sample_collection_date)='".$mnth."' AND Year(sample_collection_date)='".$year."'");
                                            //->where('vl.sample_type IN ("' . implode('", "', $sampleId) . '")');
                        if($params['facilityId'] !=''){
                            $lessThanQuery = $lessThanQuery->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
                        }
                        $lQueryStr = $sql->getSqlStringForSqlObject($lessThanQuery);
                        
                        //for($g=0;$g<2;$g++){
                        $greaterResult = $dbAdapter->query($lQueryStr." AND vl.result>1000", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $result['>18']['VL (> 1000 cp/ml)'][$j] = $greaterTotal+$greaterResult['>18'];
                        $result['<18']['VL (> 1000 cp/ml)'][$j] = $greaterTotal+$greaterResult['<18'];
                        
                        $notTargetResult = $dbAdapter->query($lQueryStr." AND 'vl.result'='Target Not Detected'", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $result['>18']['VL Not Detected'][$j] = $notTargetTotal+$notTargetResult['>18'];
                        $result['<18']['VL Not Detected'][$j] = $notTargetTotal+$notTargetResult['<18'];
                        
                        $lessResult = $dbAdapter->query($lQueryStr." AND vl.result<1000", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $result['>18']['VL (< 1000 cp/ml)'][$j] = $lessTotal+$lessResult['>18'];
                        $result['<18']['VL (< 1000 cp/ml)'][$j] = $lessTotal+$lessResult['<18'];
                        //}
                    $result['date'][$j] = $dFormat;
                    $month = strtotime("+1 month", $month);
                    $j++;
                }
            
            //\Zend\Debug\Debug::dump($result);die;
            return $result;
        }
    }
    public function fetchSampleTestedResultBasedVolumeDetails($params)
    {
        $result = array();
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService();
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
        
        $fQuery = $sql->select()->from(array('f'=>'facility_details'))->columns(array('facility_id','facility_name'))
                    ->join(array('vl'=>'dash_vl_request_form'),'vl.lab_id=f.facility_id',array('lab_id','sample_type'))
                    ->join(array('rs'=>'r_sample_type'),'rs.sample_id=vl.sample_type',array('sample_name'))
                    ->where(array("vl.sample_collection_date <='" . $endMonth ." 23:59:00". "'", "vl.sample_collection_date >='" . $startMonth." 00:00:00". "'"))
                    ->where('vl.lab_id !=0')
                    ->group('f.facility_id');
        if(isset($params['facilityId']) && trim($params['facilityId'])!=''){
            $fQuery = $fQuery->where('f.facility_id="'.base64_decode(trim($params['facilityId'])).'"');
        }
        if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
            $fQuery = $fQuery->where('rs.sample_id="'.base64_decode(trim($params['sampleType'])).'"');
        }
        $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
        $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        
        $rsQuery = $sql->select()->from(array('rs'=>'r_sample_type'))->columns(array('sample_id'));
        if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
            $rsQuery = $rsQuery->where('rs.sample_id="'.base64_decode(trim($params['sampleType'])).'"');
        }
        $rsQueryStr = $sql->getSqlStringForSqlObject($rsQuery);
        $sampleTypeResult = $dbAdapter->query($rsQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if($facilityResult && $sampleTypeResult){
            $sampleId = array();
            foreach($sampleTypeResult as $samples)
            {
                $sampleId[] = $samples['sample_id'];
            }
            $j = 0;
            $lessTotal = 0;$greaterTotal = 0;$notTargetTotal = 0;
            foreach($facilityResult as $facility)
            {
                $lessThanQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                        ->where(array("vl.sample_collection_date <='" . $endMonth ." 23:59:00". "'", "vl.sample_collection_date >='" . $startMonth." 00:00:00". "'"))
                                        //->where('vl.sample_type="'.$sample['sample_id'].'"')
                                        ->where('vl.sample_type IN ("' . implode('", "', $sampleId) . '")')
                                        ->where(array('vl.lab_id'=>$facility['facility_id']));
                $lQueryStr = $sql->getSqlStringForSqlObject($lessThanQuery);
                
                $greaterResult = $dbAdapter->query($lQueryStr." AND vl.result>1000", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $result['sampleName']['VL (> 1000 cp/ml)'][$j] = $greaterTotal+$greaterResult['total'];
                
                $notTargetResult = $dbAdapter->query($lQueryStr." AND 'vl.result'='Target Not Detected'", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $result['sampleName']['VL Not Detected'][$j] = $notTargetTotal+$notTargetResult['total'];
                
                $lessResult = $dbAdapter->query($lQueryStr." AND vl.result<1000", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $result['sampleName']['VL (< 1000 cp/ml)'][$j] = $lessTotal+$lessResult['total'];
                    
                $result['lab'][$j] = $facility['facility_name'];
                $j++;
            }
            //\Zend\Debug\Debug::dump($result);die;
        }
    }
        return $result;
    }
    
    public function getRequisitionFormsTested($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService();
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])));
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])));
            $start = $month = strtotime($startMonth);
            $end = strtotime($endMonth);
            $i = 0;
            $completeResultCount = 0;
            $inCompleteResultCount = 0;
            while($month <= $end){
                $mnth = date('m', $month);$year = date('Y', $month);$dFormat = date("M-Y", $month);
                $completeQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                         ->columns(array('total' => new Expression('COUNT(*)')))
                                         ->where("Month(sample_collection_date)='".$mnth."' AND Year(sample_collection_date)='".$year."'")
                                         ->where('vl.patient_art_no !="" AND vl.current_regimen !="" AND vl.patient_age_in_years !="" AND vl.patient_gender !=""');
                if($params['facilityId'] !=''){
                    $completeQuery = $completeQuery->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
                }
                $cQueryStr = $sql->getSqlStringForSqlObject($completeQuery);
                $completeResult = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $result['Complete'][$i] = $completeResultCount+$completeResult['total'];
                
                $inCompleteQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                           ->columns(array('total' => new Expression('COUNT(*)')))
                                           ->where("Month(sample_collection_date)='".$mnth."' AND Year(sample_collection_date)='".$year."'")
                                           ->where(array('(vl.patient_art_no="" OR vl.current_regimen="" OR vl.patient_age_in_years =""  OR vl.patient_gender="")'));
                if($params['facilityId'] !=''){
                    $inCompleteQuery = $inCompleteQuery->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
                }
                $incQueryStr = $sql->getSqlStringForSqlObject($inCompleteQuery);
                $inCompleteResult = $dbAdapter->query($incQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $result['Incomplete'][$i] = $inCompleteResultCount+$inCompleteResult['total'];
                if($completeResult['total']!=0 || $inCompleteResult['total']!=0){
                $result['date'][$i] = $dFormat;
                }else if($completeResult['total']==0 && $inCompleteResult['total']==0){
                    unset($result['Complete'][$i]);
                    unset($result['Incomplete'][$i]);
                }
                $month = strtotime("+1 month", $month);
                $i++;
            }
            return $result;
        }
    }
    
    public function fetchIncompleteSampleDetails($params){
        $result = array();
        $i =0;$j =1;$k =2;$l =3;
        $result[$i]['field'] = 'Patient ART No';
        $result[$j]['field'] = 'Current Regimen';
        $result[$k]['field'] = 'Patient Age in Years';
        $result[$l]['field'] = 'Patient Gender';
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService();
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
        }
       
        $inCompleteQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')));
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            if(trim($params['fromDate'])!= trim($params['toDate'])){
               $inCompleteQuery = $inCompleteQuery->where(array("vl.sample_collection_date >='" . $startMonth ." 00:00:00". "'", "vl.sample_collection_date <='" .$endMonth." 23:59:00". "'"));
            }else{
                $fromMonth = date("Y-m", strtotime(trim($params['fromDate'])));
                $month = strtotime($fromMonth);
                $mnth = date('m', $month);$year = date('Y', $month);
                $inCompleteQuery = $inCompleteQuery->where("Month(sample_collection_date)='".$mnth."' AND Year(sample_collection_date)='".$year."'");
            }
        }
        if(isset($params['lab']) && trim($params['lab'])!=''){
            $inCompleteQuery = $inCompleteQuery->where('vl.lab_id="'.base64_decode(trim($params['lab'])).'"');
        }
        $incQueryStr = $sql->getSqlStringForSqlObject($inCompleteQuery);
        $artInCompleteResult = $dbAdapter->query($incQueryStr." AND vl.patient_art_no =''", $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $currentRegimenInCompleteResult = $dbAdapter->query($incQueryStr." AND vl.current_regimen =''", $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $ageInYearsInCompleteResult = $dbAdapter->query($incQueryStr." AND vl.patient_age_in_years =''", $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $patientGenderInCompleteResult = $dbAdapter->query($incQueryStr." AND vl.patient_gender =''", $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $result[$i]['total'] = $artInCompleteResult->total;
        $result[$j]['total'] = $currentRegimenInCompleteResult->total;
        $result[$k]['total'] = $ageInYearsInCompleteResult->total;
        $result[$l]['total'] = $patientGenderInCompleteResult->total;
       return $result;
    }
    
    public function fetchIncompleteBarSampleDetails($params){
        $result = '';
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService();
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
        }
        $fQuery = $sql->select()->from(array('f'=>'facility_details'))
                      ->join(array('vl'=>'dash_vl_request_form'),'vl.lab_id=f.facility_id',array('lab_id','sample_type','result'))
                      ->where('vl.lab_id !=0')
                      ->group('f.facility_id');
        if(isset($params['lab']) && trim($params['lab'])!=''){
            $fQuery = $fQuery->where('vl.lab_id="'.base64_decode(trim($params['lab'])).'"');
        }
        $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
        $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if($facilityResult){
                $j = 0;
                foreach($facilityResult as $facility){
                    $countQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                      ->where('vl.lab_id="'.$facility['facility_id'].'"');
                    if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
                        if(trim($params['fromDate'])!= trim($params['toDate'])){
                           $countQuery = $countQuery->where(array("vl.sample_collection_date >='" . $startMonth ." 00:00:00". "'", "vl.sample_collection_date <='" .$endMonth." 23:59:00". "'"));
                        }else{
                            $fromMonth = date("Y-m", strtotime(trim($params['fromDate'])));
                            $month = strtotime($fromMonth);
                            $mnth = date('m', $month);$year = date('Y', $month);
                            $countQuery = $countQuery->where("Month(sample_collection_date)='".$mnth."' AND Year(sample_collection_date)='".$year."'");
                        }
                    }
                    $cQueryStr = $sql->getSqlStringForSqlObject($countQuery);
                    $completeResult = $dbAdapter->query($cQueryStr." AND vl.patient_art_no !='' AND vl.current_regimen !='' AND vl.patient_age_in_years !=''  AND vl.patient_gender != ''", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $result['form']['Complete'][$j] = $completeResult->total;
                    $inCompleteResult = $dbAdapter->query($cQueryStr." AND (vl.patient_art_no='' OR vl.current_regimen='' OR vl.patient_age_in_years =''  OR vl.patient_gender='')", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $result['form']['Incomplete'][$j] = $inCompleteResult->total;
                    $result['lab'][$j] = $facility['facility_name'];
                    $j++;
                }
        }
        return $result;
    }
    
    public function getSampleVolume($params)
    {
        $result = '';
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService();
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
        
        $fQuery = $sql->select()->from(array('f'=>'facility_details'))
                        ->join(array('vl'=>'dash_vl_request_form'),'vl.lab_id=f.facility_id',array('lab_id','sample_type'))
                        ->join(array('rs'=>'r_sample_type'),'rs.sample_id=vl.sample_type',array('sample_name'))
                        ->where(array("vl.sample_collection_date <='" . $endMonth ." 23:59:00". "'", "vl.sample_collection_date >='" . $startMonth." 00:00:00". "'"))
                        ->where('vl.lab_id !=0')
                        ->group('f.facility_id');
        if(isset($params['facilityId']) && trim($params['facilityId'])!=''){
            $fQuery = $fQuery->where('f.facility_id="'.base64_decode(trim($params['facilityId'])).'"');
        }
        if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
            //$fQuery = $fQuery->where('rs.sample_id="'.base64_decode(trim($params['sampleType'])).'"');
        }
        $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
        $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if($facilityResult){
            $i = 0;
            foreach($facilityResult as $facility){
                $countQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                    ->where(array("vl.sample_collection_date >='" . $startMonth ." 00:00:00". "'", "vl.sample_collection_date <='" .$endMonth." 23:59:00". "'"))
                                    ->where('vl.lab_id="'.$facility['facility_id'].'"');
                
                if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
                    $countQuery = $countQuery->where('vl.sample_type="'.base64_decode(trim($params['sampleType'])).'"');
                }
                $cQueryStr = $sql->getSqlStringForSqlObject($countQuery);
                $countResult[$i] = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $result[$i][0] = $countResult[$i]['total'];
                $result[$i][1] = $facility['facility_name'];
                $i++;
            }
        }
    }
        return $result;
    }
    public function fetchLabTurnAroundTime($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService();
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])));
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])));
            $rsQuery = $sql->select()->from(array('rs'=>'r_sample_type'));
            $rsQueryStr = $sql->getSqlStringForSqlObject($rsQuery);
            $sampleTypeResult = $dbAdapter->query($rsQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if($sampleTypeResult){
                $avgResult = array();$j = 0;
                $start = $month = strtotime($startMonth); $end = strtotime($endMonth);
                while($month <= $end){
                    $mnth = date('m', $month);$year = date('Y', $month);$dFormat = date("M-Y", $month);
                    $i = 0;
                    foreach($sampleTypeResult as $sample){
                        $lQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('sample_tested_datetime','sample_collection_date','diff'=>new Expression('DATEDIFF(sample_tested_datetime,sample_collection_date)')))
                                            ->where("Month(sample_collection_date)='".$mnth."' AND Year(sample_collection_date)='".$year."'")
                                            ->where(array('vl.sample_tested_datetime IS NOT NULL'))
                                            ->where('vl.sample_type="'.$sample['sample_id'].'"');
                        if($params['facilityId'] !=''){
                            $lQuery = $lQuery->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
                        }
                        $lQueryStr = $sql->getSqlStringForSqlObject($lQuery);
                        $lResult = $dbAdapter->query($lQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        if(count($lResult)>0){
                            $total = 0;
                            foreach($lResult as $data){
                                $total = $total + $data['diff'];
                            }
                            $avgResult[$sample['sample_name']][$i][$j] = round($total/count($lResult));
                        }else{
                            $avgResult[$sample['sample_name']][$i][$j] = "null";
                        }
                        $i++;
                    }
                    //all result
                    $alQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                   ->columns(array('sample_tested_datetime',
                                                   'sample_collection_date',
                                                   'diff'=>new Expression('DATEDIFF(sample_tested_datetime,sample_collection_date)')))
                                            ->where(array('vl.sample_tested_datetime IS NOT NULL'))
                                            ->where("MONTH(sample_collection_date)='".$mnth."' AND YEAR(sample_collection_date)='".$year."'");
                    if($params['facilityId'] !=''){
                        $alQuery = $alQuery->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
                    }
                    $alQueryStr = $sql->getSqlStringForSqlObject($alQuery);
                    $alResult = $dbAdapter->query($alQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    if(count($alResult)>0){
                        $total = 0;
                        foreach($alResult as $data){
                            $total = $total + $data['diff'];
                        }
                        $avgResult['all'][$j] = round($total/count($alResult));
                    }else{
                        $avgResult['all'][$j] = "null";
                    }
                    $avgResult['date'][$j] = $dFormat;
                    $month = strtotime("+1 month", $month);
                    $j++;
                }
            }
        }
        
       //\Zend\Debug\Debug::dump($avgResult);die;
        
        return $avgResult;
    }
    public function fetchFacilites($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService();
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
        
        $lQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('sample_tested_datetime','sample_collection_date','lab_id','labCount' => new \Zend\Db\Sql\Expression("COUNT(vl.lab_id)")))
                                            ->join(array('fd'=>'facility_details'),'fd.facility_id=vl.lab_id',array('facility_name','latitude','longitude'))
                                            ->where(array("vl.sample_collection_date >='" . $startMonth ." 00:00:00". "'", "vl.sample_collection_date <='" .$endMonth." 23:59:00". "'"))
                                            ->group('vl.lab_id');
        $lQueryStr = $sql->getSqlStringForSqlObject($lQuery);
        $lResult = $dbAdapter->query($lQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        
        if(count($lResult)>0){
            $i = 0;
            foreach($lResult as $lab){
                if($lab['lab_id']!='' && $lab['lab_id']!=NULL && $lab['lab_id']!=0){
                    $lcQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                            ->columns(array('sample_tested_datetime','sample_collection_date','lab_id','facility_id','vl_sample_id','clinicCount' => new \Zend\Db\Sql\Expression("COUNT(vl.facility_id)")))
                                            ->join(array('fd'=>'facility_details'),'fd.facility_id=vl.facility_id',array('facility_name','latitude','longitude'))
                                            ->where(array("vl.lab_id"=>$lab['lab_id'],'fd.facility_type'=>'1'))
                                            ->group('vl.facility_id');
                    $lcQueryStr = $sql->getSqlStringForSqlObject($lcQuery);
                    $lResult[$i]['clinic'] = $dbAdapter->query($lcQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $i++;
                }
            }
        }
    }
        //\Zend\Debug\Debug::dump($lResult);die;
        return $lResult;
    }
    
    //end lab dashboard details 
    
    //start clinic details
    public function fetchOverAllLoadStatus($params)
    {
        $common = new CommonService();
        $cDate = date('Y-m-d');
        $lastThirtyDay = date('Y-m-d', strtotime('-30 days'));
        if(isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate'])!= ''){
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
              $lastThirtyDay = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
              $cDate = trim($s_c_date[1]);
            }
        }
        
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $squery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('sample_code','sample_tested_datetime','result','sample_type'))
                        ->join(array('rst'=>'r_sample_type'),'rst.sample_id=vl.sample_type')
                        ->where(array("vl.sample_collection_date <='" . $cDate ." 23:59:00". "'", "vl.sample_collection_date >='" . $lastThirtyDay." 00:00:00". "'"))
                        ->where('vl.facility_id !=0');
        if(isset($params['clinicId']) && $params['clinicId']!=''){
            $squery = $squery->where('vl.facility_id="'.base64_decode(trim($params['clinicId'])).'"');
        }
        if(isset($params['sampleId']) && $params['sampleId']!=''){
            $squery = $squery->where('vl.sample_type="'.base64_decode(trim($params['sampleId'])).'"');
        }
        if(isset($params['testResult']) && $params['testResult']!=''){
            $squery = $squery->where('vl.result'.$params['testResult']);
        }
        
        if(isset($params['gender'] ) && trim($params['gender'])!=''){
            $squery = $squery->where(array("vl.patient_gender ='".$params['gender']."'")); 
        }
        if(isset($params['age']) && $params['age']!=''){
            $age = explode("-",$params['age']);
            if(isset($age[1])){
            $squery = $squery->where(array("vl.patient_age_in_years >='".$age[0]."'","vl.patient_age_in_years <='".$age[1]."'"));
            }else{
            $squery = $squery->where('vl.patient_age_in_years'.$params['age']);
            }
        }
        if(isset($params['adherence']) && trim($params['adherence'])!=''){
            $squery = $squery->where(array("vl.arv_adherance_percentage ='".$params['adherence']."'")); 
        }
        $sQueryStr = $sql->getSqlStringForSqlObject($squery);
        $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $sResult;
    }
    public function fetchChartOverAllLoadStatus($params)
    {
        $testedTotal = 0;$lessTotal = 0;$gTotal = 0;$overAllTotal = 0;
        //total tested
        $where = '';
        $overAllTotal = $this->fetchChartOverAllLoadResult($params,$where);
        
        $where = 'vl.result!=""';
        $testedTotal = $this->fetchChartOverAllLoadResult($params,$where);
        
        //total <1000
    
        $where = 'vl.result<1000';
        $lessTotal = $this->fetchChartOverAllLoadResult($params,$where);
        //total >1000
        $where = 'vl.result>1000';
        $gTotal = $this->fetchChartOverAllLoadResult($params,$where);
        
        return array($testedTotal,$lessTotal,$gTotal,$overAllTotal);
    }
    public function fetchSampleTestedReason($params)
    {
        $rResult = '';
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService();
        $cDate = date('Y-m-d');
        $lastThirtyDay = date('Y-m-d', strtotime('-30 days'));
        if(isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate'])!= ''){
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
              $lastThirtyDay = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
              $cDate = trim($s_c_date[1]);
            }
        }
        $sResult = $this->getDistinctDate($cDate,$lastThirtyDay);
        $rsQuery = $sql->select()->from(array('r'=>'r_vl_test_reasons'));
        if(isset($params['testReason']) && $params['testReason']!=''){
            $rsQuery = $rsQuery->where('r.test_reason_id="'.base64_decode(trim($params['testReason'])).'"');
        }
        $rsQueryStr = $sql->getSqlStringForSqlObject($rsQuery);
        $testReason = $dbAdapter->query($rsQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if($sResult && $testReason){
             $i = 0;
            foreach($testReason as $reason){
                $j = 0;
                foreach($sResult as $sampleData){
                    if($sampleData['year']!=NULL){
                        $date = $sampleData['year']."-".$sampleData['month']."-".$sampleData['day'];
                        $dFormat = date("d M", strtotime($date));
                        $rQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                        ->join(array('rst'=>'r_sample_type'),'rst.sample_id=vl.sample_type')
                                        ->where(array("vl.sample_collection_date >='" . $date ." 00:00:00". "'", "vl.sample_collection_date <='" . $date." 23:59:00". "'"))
                                        ->where('vl.facility_id !=0')
                                        ->where('vl.reason_for_vl_testing="'.$reason['test_reason_id'].'"');
                        if(isset($params['facilityId']) && $params['facilityId']!=''){
                            $rQuery = $rQuery->where('vl.facility_id="'.base64_decode(trim($params['facilityId'])).'"');
                        }
                        if(isset($params['sampleId']) && $params['sampleId']!=''){
                            $rQuery = $rQuery->where('vl.sample_type="'.base64_decode(trim($params['sampleId'])).'"');
                        }
                        if(isset($params['testResult']) && $params['testResult']!=''){
                            $rQuery = $rQuery->where('vl.result'.$params['testResult']);
                        }
                        if(isset($params['gender'] ) && trim($params['gender'])!=''){
                            $rQuery = $rQuery->where(array("vl.patient_gender ='".$params['gender']."'")); 
                        }
                        if(isset($params['age']) && $params['age']!=''){
                            $age = explode("-",$params['age']);
                            if(isset($age[1])){
                            $rQuery = $rQuery->where(array("vl.patient_age_in_years >='".$age[0]."'","vl.patient_age_in_years <='".$age[1]."'"));
                            }else{
                            $rQuery = $rQuery->where('vl.patient_age_in_years'.$params['age']);
                            }
                        }
                        $rQueryStr = $sql->getSqlStringForSqlObject($rQuery);
                        $rResult[$reason['test_reason_name']][$j] = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $rResult['date'][$j] = $dFormat;
                    }
                    $j++;
                    }
                    $i++;
                }
            }
        return $rResult;
    }
    public function fetchChartOverAllLoadResult($params,$where)
    {
        $common = new CommonService();
        $cDate = date('Y-m-d');
        $lastThirtyDay = date('Y-m-d', strtotime('-30 days'));
        if(isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate'])!= ''){
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
              $lastThirtyDay = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
              $cDate = trim($s_c_date[1]);
            }
        }
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $squery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                        ->join(array('rst'=>'r_sample_type'),'rst.sample_id=vl.sample_type')
                        ->where(array("vl.sample_collection_date <='" . $cDate ." 23:59:00". "'", "vl.sample_collection_date >='" . $lastThirtyDay." 00:00:00". "'"))
                        ->where('vl.facility_id !=0');
        if(isset($params['clinicId']) && $params['clinicId']!=''){
            $squery = $squery->where('vl.facility_id="'.base64_decode(trim($params['clinicId'])).'"');
        }
        if(isset($params['sampleId']) && $params['sampleId']!=''){
            $squery = $squery->where('vl.sample_type="'.base64_decode(trim($params['sampleId'])).'"');
        }
        if(isset($params['testResult']) && $params['testResult']!=''){
            $squery = $squery->where('vl.result'.$params['testResult']);
        }
        
        if(isset($params['gender'] ) && trim($params['gender'])!=''){
            $squery = $squery->where(array("vl.patient_gender ='".$params['gender']."'")); 
        }
        if(isset($params['age']) && $params['age']!=''){
            $age = explode("-",$params['age']);
            if(isset($age[1])){
            $squery = $squery->where(array("vl.patient_age_in_years >='".$age[0]."'","vl.patient_age_in_years <='".$age[1]."'"));
            }else{
            $squery = $squery->where('vl.patient_age_in_years'.$params['age']);
            }
        }
        if($where!=''){
        $squery = $squery->where($where);    
        }
        $sQueryStr = $sql->getSqlStringForSqlObject($squery);
        $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $sResult;
    }
    //end clinic details
    
    //get distinict date
    public function getDistinctDate($cDate,$lastThirtyDay)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $squery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                            ->columns(array(new Expression('DISTINCT YEAR(sample_collection_date) as year,MONTH(sample_collection_date) as month,DAY(sample_collection_date) as day')))
                            //->where('vl.lab_id !=0')
                            ->order('month ASC')->order('day ASC');
        if(isset($cDate) && trim($cDate)!= ''){
            $squery = $squery->where(array("vl.sample_collection_date <='" . $cDate ." 23:59:00". "'", "vl.sample_collection_date >='" . $lastThirtyDay." 00:00:00". "'"));
        }
        $sQueryStr = $sql->getSqlStringForSqlObject($squery);
        $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $sResult;
    }
    
    public function fetchAllTestResults($parameters) {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('sample_code','DATE_FORMAT(sample_collection_date,"%d-%b-%Y")','DATE_FORMAT(sample_testing_date,"%d-%b-%Y")','result');
        $orderColumns = array('sample_code','sample_collection_date','sample_testing_date','result');

        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */

        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ( $parameters['sSortDir_' . $i] ) . ",";
                }
            }
            $sOrder = substr_replace($sOrder, "", -1);
        }

        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */

        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
        */
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                ->columns(array('vl_sample_id','sample_code','sample_collection_date','sample_type','sample_testing_date','result_value_log','result_value_absolute','result_value_text','result'))
				->join(array('fd'=>'facility_details'),'fd.facility_id=vl.facility_id',array('facility_name'))
				->where(array('fd.facility_type'=>'1'));
        $cDate = ''; $lastThirtyDay = '';
	if(isset($parameters['sampleCollectionDate']) && trim($parameters['sampleCollectionDate'])!= ''){
            $s_c_date = explode("to", $parameters['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
              $lastThirtyDay = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
              $cDate = trim($s_c_date[1]);
            }
        }
        if($cDate!='' && $lastThirtyDay!='')
        {
            $sQuery = $sQuery->where(array("vl.sample_collection_date <='" . $cDate ." 23:59:00". "'", "vl.sample_collection_date >='" . $lastThirtyDay." 00:00:00". "'"));
        }
        if($parameters['clinicId'] !=''){
            $sQuery = $sQuery->where(array("vl.facility_id ='".base64_decode(trim($parameters['clinicId']))."'")); 
        }
        if(isset($parameters['gender'] ) && trim($parameters['gender'])!=''){
            $sQuery = $sQuery->where(array("vl.patient_gender ='".$parameters['gender']."'")); 
        }
        if(isset($parameters['sampleId'] ) && trim($parameters['sampleId'])!=''){
            $sQuery = $sQuery->where('vl.sample_type="'.base64_decode(trim($parameters['sampleId'])).'"');
        }
        if(isset($parameters['age']) && trim($parameters['age'])!=''){
            $expAge=explode("-",$parameters['age']);
            if(trim($expAge[0])!="" && trim($expAge[1])!=""){
                $sQuery=$sQuery->where("(vl.patient_age_in_years>='".$expAge[0]."' AND vl.patient_age_in_years<='".$expAge[1]."')");
            }else{
                $sQuery = $sQuery->where(array("vl.patient_age_in_years >'".$expAge[0]."'"));
            }
        }
        if(isset($parameters['adherence']) && trim($parameters['adherence'])!=''){
            $sQuery = $sQuery->where(array("vl.arv_adherance_percentage ='".$parameters['adherence']."'")); 
        }
        
        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery->limit($sLimit);
            $sQuery->offset($sOffset);
        }

        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery); // Get the string of the Sql, instead of the Select-instance 
        //echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->getSqlStringForSqlObject($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('vl_sample_id'))
				->join(array('fd'=>'facility_details'),'fd.facility_id=vl.facility_id',array('facility_name'))
				->where(array('fd.facility_type'=>'1'));
        $iQueryStr = $sql->getSqlStringForSqlObject($iQuery);
        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);
        
        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );
        
		$common = new CommonService();
        foreach ($rResult as $aRow) {
            $row = array();
			if(isset($aRow['sample_collection_date']) && trim($aRow['sample_collection_date'])!=""){
                $xepCollectDate=explode(" ",$aRow['sample_collection_date']);
                $aRow['sample_collection_date']=$common->humanDateFormat($xepCollectDate[0])." ".$xepCollectDate[1];
            }
            if(isset($aRow['sample_testing_date']) && trim($aRow['sample_testing_date'])!=""){
                $xepTestingDate=explode(" ",$aRow['sample_testing_date']);
                $aRow['sample_testing_date']=$common->humanDateFormat($xepTestingDate[0])." ".$xepTestingDate[1];
            }
            $row[] = $aRow['sample_code'];
            $row[] = $aRow['sample_collection_date'];
            $row[] = $aRow['sample_testing_date'];
			$row[] = $aRow['result'];
			$row[]='<a href="#" class="btn btn-primary btn-xs">View</a>';
            
            $output['aaData'][] = $row;
        }
        return $output;
    }
    
    //get sample tested result details
    public function fetchClinicSampleTestedResults($params)
    {
        $result = array();
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService();
        $cDate = date('Y-m-d');
        $lastThirtyDay = date('Y-m-d', strtotime('-30 days'));
        if(isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate'])!= ''){
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
              $lastThirtyDay = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
              $cDate = trim($s_c_date[1]);
            }
        }
        
        $rsQuery = $sql->select()->from(array('rs'=>'r_sample_type'));
        if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
            $rsQuery = $rsQuery->where('rs.sample_id="'.base64_decode(trim($params['sampleType'])).'"');
        }
        $rsQueryStr = $sql->getSqlStringForSqlObject($rsQuery);
        $sampleTypeResult = $dbAdapter->query($rsQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if($sampleTypeResult){
            //set datewise query
            $sResult = $this->getClinicDistinicDate($cDate,$lastThirtyDay);
            $j = 0;
            if($sResult){
                foreach($sResult as $sampleData){
                    if($sampleData['year']!=NULL){
                        $date = $sampleData['year']."-".$sampleData['month']."-".$sampleData['day'];
                        $dFormat = date("d M", strtotime($date));
                        $i = 0;
                        $lessTotal = 0;$greaterTotal = 0;$notTargetTotal = 0;
                        foreach($sampleTypeResult as $sample){
                            if(isset($params['testResult']) && ($params['testResult']=="" || $params['testResult']=='<1000')){
                                $lessThanQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                                ->join(array('fd'=>'facility_details'),'fd.facility_id=vl.facility_id',array('facility_name'))
                                                ->where(array('fd.facility_type'=>'1'))
                                                ->where(array("vl.sample_collection_date >='" . $date ." 00:00:00". "'", "vl.sample_collection_date <='" . $date." 23:59:00". "'"))
                                                ->where('vl.sample_type="'.$sample['sample_id'].'"')
                                                ->where(array('vl.result<1000'));
                                if($params['facilityId'] !=''){
                                    $lessThanQuery = $lessThanQuery->where(array("vl.facility_id ='".base64_decode($params['facilityId'])."'")); 
                                }
                                if(isset($params['gender'] ) && trim($params['gender'])!=''){
                                    $lessThanQuery = $lessThanQuery->where(array("vl.patient_gender ='".$params['gender']."'")); 
                                }
                                if(isset($params['age']) && trim($params['age'])!=''){
                                    $expAge=explode("-",$params['age']);
                                    if(trim($expAge[0])!="" && trim($expAge[1])!=""){
                                        $lessThanQuery=$lessThanQuery->where("(vl.patient_age_in_years>='".$expAge[0]."' AND vl.patient_age_in_years<='".$expAge[1]."')");
                                    }else{
                                        $lessThanQuery = $lessThanQuery->where(array("vl.patient_age_in_years >'".$expAge[0]."'"));
                                    }
                                }
                                if(isset($params['adherence']) && trim($params['adherence'])!=''){
                                    $lessThanQuery = $lessThanQuery->where(array("vl.arv_adherance_percentage ='".$params['adherence']."'")); 
                                }
                                $lQueryStr = $sql->getSqlStringForSqlObject($lessThanQuery);
                                $lessResult[$i] = $dbAdapter->query($lQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                $result[$sample['sample_name']]['VL (< 1000 cp/ml)'][$j] = $lessTotal+$lessResult[$i]['total'];
                            }
                            
                            if(isset($params['testResult']) && ($params['testResult']=="" || $params['testResult']=='>1000')){
                                $greaterThanQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                                    ->join(array('fd'=>'facility_details'),'fd.facility_id=vl.facility_id',array('facility_name'))
                                                    ->where(array('fd.facility_type'=>'1'))
                                                    ->where(array("vl.sample_collection_date >='" . $date ." 00:00:00". "'", "vl.sample_collection_date <='" . $date." 23:59:00". "'"))
                                                    ->where('vl.sample_type="'.$sample['sample_id'].'"')
                                                    ->where(array('vl.result>1000'));
                                if($params['facilityId'] !=''){
                                    $greaterThanQuery = $greaterThanQuery->where(array("vl.facility_id ='".base64_decode($params['facilityId'])."'")); 
                                }
                                if(isset($params['gender'] ) && trim($params['gender'])!=''){
                                    $greaterThanQuery = $greaterThanQuery->where(array("vl.patient_gender ='".$params['gender']."'")); 
                                }
                                if(isset($params['age']) && trim($params['age'])!=''){
                                    $expAge=explode("-",$params['age']);
                                    if(trim($expAge[0])!="" && trim($expAge[1])!=""){
                                        $greaterThanQuery=$greaterThanQuery->where("(vl.patient_age_in_years>='".$expAge[0]."' AND vl.patient_age_in_years<='".$expAge[1]."')");
                                    }else{
                                        $greaterThanQuery = $greaterThanQuery->where(array("vl.patient_age_in_years >'".$expAge[0]."'"));
                                    }
                                }
                                if(isset($params['adherence']) && trim($params['adherence'])!=''){
                                    $greaterThanQuery = $greaterThanQuery->where(array("vl.arv_adherance_percentage ='".$params['adherence']."'")); 
                                }
                                $gQueryStr = $sql->getSqlStringForSqlObject($greaterThanQuery);
                                $greaterResult[$i] = $dbAdapter->query($gQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                $result[$sample['sample_name']]['VL (> 1000 cp/ml)'][$j] = $greaterTotal+$greaterResult[$i]['total'];
                            }
                            
                            if(isset($params['testResult']) && $params['testResult']==""){
                                $notDetectQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                                ->join(array('fd'=>'facility_details'),'fd.facility_id=vl.facility_id',array('facility_name'))
                                                ->where(array('fd.facility_type'=>'1'))
                                                ->where(array("vl.sample_collection_date >='" . $date ." 00:00:00". "'", "vl.sample_collection_date <='" . $date." 23:59:00". "'"))
                                                ->where('vl.sample_type="'.$sample['sample_id'].'"')
                                                ->where(array('vl.result'=>'Target Not Detected'));
                                if($params['facilityId'] !=''){
                                    $notDetectQuery = $notDetectQuery->where(array("vl.facility_id ='".base64_decode($params['facilityId'])."'")); 
                                }
                                if($params['gender'] !=''){
                                    $notDetectQuery = $notDetectQuery->where(array("vl.patient_gender ='".$params['gender']."'")); 
                                }
                                if(isset($params['age']) && trim($params['age'])!=''){
                                    $expAge=explode("-",$params['age']);
                                    if(trim($expAge[0])!="" && trim($expAge[1])!=""){
                                        $notDetectQuery=$notDetectQuery->where("(vl.patient_age_in_years>='".$expAge[0]."' AND vl.patient_age_in_years<='".$expAge[1]."')");
                                    }else{
                                        $notDetectQuery = $notDetectQuery->where(array("vl.patient_age_in_years >'".$expAge[0]."'"));
                                    }
                                }
                                if(isset($params['adherence']) && trim($params['adherence'])!=''){
                                    $notDetectQuery = $notDetectQuery->where(array("vl.arv_adherance_percentage ='".$params['adherence']."'")); 
                                }
                                $nQueryStr = $sql->getSqlStringForSqlObject($notDetectQuery);
                                $notTargetResult[$i] = $dbAdapter->query($nQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                $result[$sample['sample_name']]['VL Not Detected'][$j] = $notTargetTotal+$notTargetResult[$i]['total'];
                            }
                            $i++;
                        }
                        $result['date'][$j] = $dFormat;
                        $j++;
                    }
                }
            }
            //\Zend\Debug\Debug::dump($result);die;
            return $result;
        }
    }
    
    public function getClinicDistinicDate($cDate,$lastThirtyDay)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $squery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                            ->columns(array(new Expression('DISTINCT YEAR(sample_collection_date) as year,MONTH(sample_collection_date) as month,DAY(sample_collection_date) as day')))
                            ->join(array('fd'=>'facility_details'),'fd.facility_id=vl.facility_id',array('facility_name'))
                            ->where(array('fd.facility_type'=>'1'))
                            ->where('vl.lab_id !=0')
                            ->order('month ASC')->order('day ASC');
        if(isset($cDate) && trim($cDate)!= ''){
            $squery = $squery->where(array("vl.sample_collection_date <='" . $cDate ." 23:59:00". "'", "vl.sample_collection_date >='" . $lastThirtyDay." 00:00:00". "'"));
        }
        $sQueryStr = $sql->getSqlStringForSqlObject($squery);
        $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $sResult;
    }
	
    public function fetchSampleDetails($params){
		//\Zend\Debug\Debug::dump($params);
		//die;
                $result = '';
                $dbAdapter = $this->adapter;
                $sql = new Sql($dbAdapter);
                $common = new CommonService();
                if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
                    $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
                    $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
                }
                $fQuery = $sql->select()->from(array('f'=>'facility_details'))
                ->join(array('vl'=>'dash_vl_request_form'),'vl.lab_id=f.facility_id',array('lab_id','sample_type','result'))
                ->where('vl.lab_id !=0')
                ->group('f.facility_id');
                                        
                if(isset($params['facilityId']) && trim($params['facilityId'])!=''){
                    $fQuery = $fQuery->where('f.facility_id="'.base64_decode(trim($params['facilityId'])).'"');
                }
                
                $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
                $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if($facilityResult){
                        $i = 0;
                        foreach($facilityResult as $facility){
                                $countQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                                                        ->where('vl.lab_id="'.$facility['facility_id'].'"');
                                
                                if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
                                        $countQuery = $countQuery->where('rs.sample_id="'.base64_decode(trim($params['sampleType'])).'"');
                                }
                                if(isset($params['testResult']) && $params['testResult']!=''){
                                    if($params['testResult'] == '<1000'){
                                      $countQuery = $countQuery->where("vl.result < 1000");
                                    }else if($params['testResult'] == '>1000') {
                                      $countQuery = $countQuery->where("vl.result > 1000");
                                    }
                                }
                                if(isset($params['gender']) && trim($params['gender'])!=''){
                                        $countQuery = $countQuery->where('vl.patient_gender="'.$params['gender'].'"');
                                }
                                if(isset($params['currentRegimen']) && trim($params['currentRegimen'])!=''){
                                        $countQuery = $countQuery->where('vl.current_regimen="'.base64_decode(trim($params['currentRegimen'])).'"');
                                }
                                
                                if(isset($params['adherence']) && trim($params['adherence'])!=''){
                                        $countQuery = $countQuery->where(array("vl.arv_adherance_percentage ='".$params['adherence']."'")); 
                                }
                                
                                if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
                                        if(trim($params['fromDate'])!= trim($params['toDate'])){
                                           $countQuery = $countQuery->where(array("vl.sample_collection_date >='" . $startMonth ." 00:00:00". "'", "vl.sample_collection_date <='" .$endMonth." 23:59:00". "'"));
                                        }else{
                                            $fromMonth = date("Y-m", strtotime(trim($params['fromDate'])));
                                            $month = strtotime($fromMonth);
                                            $mnth = date('m', $month);$year = date('Y', $month);
                                            $countQuery = $countQuery->where("Month(sample_collection_date)='".$mnth."' AND Year(sample_collection_date)='".$year."'");
                                        }
                                }
                                
                                if(isset($params['age']) && $params['age']!=''){
                                        if($params['age'] == '<18'){
                                          $countQuery = $countQuery->where("vl.patient_age_in_years < 18");
                                        }else if($params['age'] == '>18') {
                                          $countQuery = $countQuery->where("vl.patient_age_in_years > 18");
                                        }else if($params['age'] == 'unknown'){
                                          $countQuery = $countQuery->where("vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL");
                                        }
                                }
                                
                                $cQueryStr = $sql->getSqlStringForSqlObject($countQuery);
                                //echo $cQueryStr;die;
                                $countResult[$i] = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                $result[$i][0] = $countResult[$i]['total'];
                                $result[$i][1] = $facility['facility_name'];
                                $i++;
                        }
                }
		//\Zend\Debug\Debug::dump($result);
		//die;
        return $result;
    }
    
    public function fetchBarSampleDetails($params){
        //\Zend\Debug\Debug::dump($params);
		//die;
                $result = '';
                $dbAdapter = $this->adapter;
                $sql = new Sql($dbAdapter);
                $common = new CommonService();
                if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
                    $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
                    $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
                }
                $fQuery = $sql->select()->from(array('f'=>'facility_details'))
                ->join(array('vl'=>'dash_vl_request_form'),'vl.lab_id=f.facility_id',array('lab_id','sample_type','result'))
                ->where('vl.lab_id !=0')
                ->group('f.facility_id');
                                        
                if(isset($params['facilityId']) && trim($params['facilityId'])!=''){
                    $fQuery = $fQuery->where('f.facility_id="'.base64_decode(trim($params['facilityId'])).'"');
                }
                
                $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
                $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if($facilityResult){
                        $j = 0;
                        foreach($facilityResult as $facility){
                            $countQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                                                        ->where('vl.lab_id="'.$facility['facility_id'].'"');
                            if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
                                    $countQuery = $countQuery->where('rs.sample_id="'.base64_decode(trim($params['sampleType'])).'"');
                            }
                            if(isset($params['testResult']) && $params['testResult']!=''){
                                if($params['testResult'] == '<1000'){
                                  $countQuery = $countQuery->where("vl.result < 1000");
                                }else if($params['testResult'] == '>1000') {
                                  $countQuery = $countQuery->where("vl.result > 1000");
                                }
                            }
                            if(isset($params['gender']) && trim($params['gender'])!=''){
                                    $countQuery = $countQuery->where('vl.patient_gender="'.$params['gender'].'"');
                            }
                            if(isset($params['currentRegimen']) && trim($params['currentRegimen'])!=''){
                                    $countQuery = $countQuery->where('vl.current_regimen="'.base64_decode(trim($params['currentRegimen'])).'"');
                            }
                            
                            if(isset($params['adherence']) && trim($params['adherence'])!=''){
                                    $countQuery = $countQuery->where(array("vl.arv_adherance_percentage ='".$params['adherence']."'")); 
                            }
                            
                            if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
                                    if(trim($params['fromDate'])!= trim($params['toDate'])){
                                       $countQuery = $countQuery->where(array("vl.sample_collection_date >='" . $startMonth ." 00:00:00". "'", "vl.sample_collection_date <='" .$endMonth." 23:59:00". "'"));
                                    }else{
                                        $fromMonth = date("Y-m", strtotime(trim($params['fromDate'])));
                                        $month = strtotime($fromMonth);
                                        $mnth = date('m', $month);$year = date('Y', $month);
                                        $countQuery = $countQuery->where("Month(sample_collection_date)='".$mnth."' AND Year(sample_collection_date)='".$year."'");
                                    }
                            }
                            
                            if(isset($params['age']) && $params['age']!=''){
                                    if($params['age'] == '<18'){
                                      $countQuery = $countQuery->where("vl.patient_age_in_years < 18");
                                    }else if($params['age'] == '>18') {
                                      $countQuery = $countQuery->where("vl.patient_age_in_years > 18");
                                    }else if($params['age'] == 'unknown'){
                                      $countQuery = $countQuery->where("vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL");
                                    }
                            }
                            $cQueryStr = $sql->getSqlStringForSqlObject($countQuery);
                            $lessResult = $dbAdapter->query($cQueryStr." AND vl.result < 1000", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $result['sample']['Suppressed'][$j] = $lessResult->total;
                            $greaterResult = $dbAdapter->query($cQueryStr." AND vl.result > 1000", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $result['sample']['Not Suppressed'][$j] = $greaterResult->total;
                            $rejectionResult = $dbAdapter->query($cQueryStr." AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != 0", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $result['sample']['Rejected'][$j] = $rejectionResult->total;
                            $result['lab'][$j] = $facility['facility_name'];
                            $j++;
                        }
                }
		//\Zend\Debug\Debug::dump($result);
		//die;
        return $result;
    }
    
    public function fetchLabSampleDetails($params){
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService();
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
        }
        $sQuery = $sql->select()->from(array('rs'=>'r_sample_type'))
                      ->columns(array('sample_name'))
                      ->join(array('vl'=>'dash_vl_request_form'),'rs.sample_id=vl.sample_type',array('samples' => new Expression('COUNT(*)')))
                      ->group('vl.sample_type');
        if(isset($params['lab']) && trim($params['lab'])!=''){
            $sQuery = $sQuery->where('vl.lab_id="'.base64_decode(trim($params['lab'])).'"');
        }
        if(isset($params['clinicId']) && trim($params['clinicId'])!=''){
            $sQuery = $sQuery->where('vl.facility_id="'.base64_decode(trim($params['clinicId'])).'"');
        }
        if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
                $sQuery = $sQuery->where('rs.sample_id="'.base64_decode(trim($params['sampleType'])).'"');
        }
        if(isset($params['testResult']) && $params['testResult']!=''){
            if($params['testResult'] == '<1000'){
              $sQuery = $sQuery->where("vl.result < 1000");
            }else if($params['testResult'] == '>1000') {
              $sQuery = $sQuery->where("vl.result > 1000");
            }
        }
        if(isset($params['gender']) && trim($params['gender'])!=''){
                $sQuery = $sQuery->where('vl.patient_gender="'.$params['gender'].'"');
        }
        if(isset($params['currentRegimen']) && trim($params['currentRegimen'])!=''){
                $sQuery = $sQuery->where('vl.current_regimen="'.base64_decode(trim($params['currentRegimen'])).'"');
        }
        
        if(isset($params['adherence']) && trim($params['adherence'])!=''){
                $sQuery = $sQuery->where(array("vl.arv_adherance_percentage ='".$params['adherence']."'")); 
        }
        
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
                if(trim($params['fromDate'])!= trim($params['toDate'])){
                   $sQuery = $sQuery->where(array("vl.sample_collection_date >='" . $startMonth ." 00:00:00". "'", "vl.sample_collection_date <='" .$endMonth." 23:59:00". "'"));
                }else{
                    $fromMonth = date("Y-m", strtotime(trim($params['fromDate'])));
                    $month = strtotime($fromMonth);
                    $mnth = date('m', $month);$year = date('Y', $month);
                    $sQuery = $sQuery->where("Month(sample_collection_date)='".$mnth."' AND Year(sample_collection_date)='".$year."'");
                }
        }
        
        if(isset($params['age']) && $params['age']!=''){
                if($params['age'] == '<18'){
                  $sQuery = $sQuery->where("vl.patient_age_in_years < 18");
                }else if($params['age'] == '>18') {
                  $sQuery = $sQuery->where("vl.patient_age_in_years > 18");
                }else if($params['age'] == 'unknown'){
                  $sQuery = $sQuery->where("vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL");
                }
        }
        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);
        return $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
    }
    
    public function fetchLabBarSampleDetails($params){
        $result = array();
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $dbAdapter = $this->adapter;
            $sql = new Sql($dbAdapter);
            $common = new CommonService();
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])));
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])));
            $start = $month = strtotime($startMonth);
            $end = strtotime($endMonth);
            $j = 0;
            while($month <= $end){
                $monthPlus = date('m', $month);$year = date('Y', $month);$dFormat = date("M-Y", $month);
                $sQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('samples' => new Expression('COUNT(*)')))
                              ->where("Month(sample_collection_date)='".$monthPlus."' AND Year(sample_collection_date)='".$year."'");
                if(isset($params['lab']) && trim($params['lab'])!=''){
                    $sQuery = $sQuery->where('vl.lab_id="'.base64_decode(trim($params['lab'])).'"');
                }
                if(isset($params['clinicId']) && trim($params['clinicId'])!=''){
                    $sQuery = $sQuery->where('vl.facility_id="'.base64_decode(trim($params['clinicId'])).'"');
                }
                if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
                        $sQuery = $sQuery->where('rs.sample_id="'.base64_decode(trim($params['sampleType'])).'"');
                }
                if(isset($params['testResult']) && $params['testResult']!=''){
                    if($params['testResult'] == '<1000'){
                      $sQuery = $sQuery->where("vl.result < 1000");
                    }else if($params['testResult'] == '>1000') {
                      $sQuery = $sQuery->where("vl.result > 1000");
                    }
                }
                if(isset($params['gender']) && trim($params['gender'])!=''){
                        $sQuery = $sQuery->where('vl.patient_gender="'.$params['gender'].'"');
                }
                if(isset($params['currentRegimen']) && trim($params['currentRegimen'])!=''){
                        $sQuery = $sQuery->where('vl.current_regimen="'.base64_decode(trim($params['currentRegimen'])).'"');
                }
                
                if(isset($params['adherence']) && trim($params['adherence'])!=''){
                        $sQuery = $sQuery->where(array("vl.arv_adherance_percentage ='".$params['adherence']."'")); 
                }
                
                if(isset($params['age']) && $params['age']!=''){
                        if($params['age'] == '<18'){
                          $sQuery = $sQuery->where("vl.patient_age_in_years < 18");
                        }else if($params['age'] == '>18') {
                          $sQuery = $sQuery->where("vl.patient_age_in_years > 18");
                        }else if($params['age'] == 'unknown'){
                          $sQuery = $sQuery->where("vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL");
                        }
                }
                $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);
                //echo $sQueryStr;die;
                $lessResult = $dbAdapter->query($sQueryStr." AND vl.result<1000", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $result['rslt']['VL (< 1000 cp/ml)'][$j] = $lessResult->samples;
                
                $greaterResult = $dbAdapter->query($sQueryStr." AND vl.result>1000", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $result['rslt']['VL (> 1000 cp/ml)'][$j] = $greaterResult->samples;
                
                $notTargetResult = $dbAdapter->query($sQueryStr." AND 'vl.result'='Target Not Detected'", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $result['rslt']['VL Not Detected'][$j] = $notTargetResult->samples;
                $result['date'][$j] = $dFormat;
                $month = strtotime("+1 month", $month);
              $j++;
            }
        }
       return $result;
    }
    
    public function fetchFilterSampleDetails($parameters){
        $common = new CommonService();
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('DATE_FORMAT(sample_collection_date,"%d-%b-%Y")','vl_sample_id','sample_name','facility_name');
        $orderColumns = array('sample_collection_date','vl_sample_id','sample_name','facility_name');

        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */

        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ( $parameters['sSortDir_' . $i] ) . ",";
                }
            }
            $sOrder = substr_replace($sOrder, "", -1);
        }

        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */

        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
        */
        if(trim($parameters['fromDate'])!= '' && trim($parameters['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($parameters['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($parameters['toDate'])))."-31";
        }
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(array('sampleCollectionDate'=>new Expression('DATE(sample_collection_date)'),'samples' => new Expression('COUNT(*)')))
				->join(array('fd'=>'facility_details'),'fd.facility_id=vl.facility_id',array('facility_name'))
				->join(array('rs'=>'r_sample_type'),'rs.sample_id=vl.sample_type',array('sample_name'))
				->where('fd.facility_type = "1" AND vl.sample_collection_date!= "" AND vl.sample_collection_date IS NOT NULL AND vl.sample_collection_date!= "0000-00-00 00:00:00"')
                                ->group(new Expression('DATE(sample_collection_date)'))
                                ->group('vl.sample_type')
                                ->group('vl.facility_id');
        //filter start
        if(trim($parameters['fromDate'])!= '' && trim($parameters['toDate'])!= ''){
            if(trim($parameters['fromDate'])!= trim($parameters['toDate'])){
               $sQuery = $sQuery->where(array("vl.sample_collection_date >='" . $startMonth ." 00:00:00". "'", "vl.sample_collection_date <='" .$endMonth." 23:59:00". "'"));
            }else{
                $fromMonth = date("Y-m", strtotime(trim($parameters['fromDate'])));
                $month = strtotime($fromMonth);
                $mnth = date('m', $month);$year = date('Y', $month);
                $sQuery = $sQuery->where("Month(sample_collection_date)='".$mnth."' AND Year(sample_collection_date)='".$year."'");
            }
        }if(isset($parameters['searchGender'] ) && trim($parameters['searchGender'])!=''){
            $sQuery = $sQuery->where(array("vl.patient_gender ='".$parameters['searchGender']."'")); 
        }if(isset($parameters['testResult']) && $parameters['testResult']!=''){
            if($parameters['testResult'] == '<1000'){
              $sQuery = $sQuery->where("vl.result < 1000");
            }else if($parameters['testResult'] == '>1000') {
              $sQuery = $sQuery->where("vl.result > 1000");
            }
        }if(isset($parameters['clinic'] ) && trim($parameters['clinic'])!=''){
            $sQuery = $sQuery->where(array("vl.facility_id ='".base64_decode($parameters['clinic'])."'")); 
        }if(isset($parameters['lab'] ) && trim($parameters['lab'])!=''){
            $sQuery = $sQuery->where(array("vl.lab_id ='".base64_decode($parameters['lab'])."'")); 
        }if(isset($parameters['sampleType']) && trim($parameters['sampleType'])!=''){
            $sQuery = $sQuery->where('vl.sample_type="'.base64_decode(trim($parameters['sampleType'])).'"');
        }if(isset($parameters['currentRegimen']) && trim($parameters['currentRegimen'])!=''){
            $sQuery = $sQuery->where('vl.current_regimen="'.base64_decode(trim($parameters['currentRegimen'])).'"');
        }if(isset($parameters['adherence']) && trim($parameters['adherence'])!=''){
            $sQuery = $sQuery->where(array("vl.arv_adherance_percentage ='".$parameters['adherence']."'")); 
        }if(isset($parameters['age']) && $parameters['age']!=''){
            if($parameters['age'] == '<18'){
              $sQuery = $sQuery->where("vl.patient_age_in_years < 18");
            }else if($parameters['age'] == '>18') {
              $sQuery = $sQuery->where("vl.patient_age_in_years > 18");
            }else if($parameters['age'] == 'unknown'){
              $sQuery = $sQuery->where("vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL");
            }
        }
        
        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery->limit($sLimit);
            $sQuery->offset($sOffset);
        }

        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery); // Get the string of the Sql, instead of the Select-instance
        //echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->getSqlStringForSqlObject($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(array('sampleCollectionDate'=>new Expression('DATE(sample_collection_date)'),'samples' => new Expression('COUNT(*)')))
				->join(array('fd'=>'facility_details'),'fd.facility_id=vl.facility_id',array('facility_name'))
				->join(array('rs'=>'r_sample_type'),'rs.sample_id=vl.sample_type',array('sample_name'))
				->where('fd.facility_type = "1" AND vl.sample_collection_date!= "" AND vl.sample_collection_date IS NOT NULL AND vl.sample_collection_date!= "0000-00-00 00:00:00"')
                                ->group(new Expression('DATE(sample_collection_date)'))
                                ->group('vl.sample_type')
                                ->group('vl.facility_id');
        //filter start
        if(trim($parameters['fromDate'])!= '' && trim($parameters['toDate'])!= ''){
            if(trim($parameters['fromDate'])!= trim($parameters['toDate'])){
               $iQuery = $iQuery->where(array("vl.sample_collection_date >='" . $startMonth ." 00:00:00". "'", "vl.sample_collection_date <='" .$endMonth." 23:59:00". "'"));
            }else{
                $fromMonth = date("Y-m", strtotime(trim($parameters['fromDate'])));
                $month = strtotime($fromMonth);
                $mnth = date('m', $month);$year = date('Y', $month);
                $iQuery = $iQuery->where("Month(sample_collection_date)='".$mnth."' AND Year(sample_collection_date)='".$year."'");
            }
        }if(isset($parameters['searchGender'] ) && trim($parameters['searchGender'])!=''){
            $iQuery = $iQuery->where(array("vl.patient_gender ='".$parameters['searchGender']."'")); 
        }if(isset($parameters['testResult']) && $parameters['testResult']!=''){
            if($parameters['testResult'] == '<1000'){
              $iQuery = $iQuery->where("vl.result < 1000");
            }else if($parameters['testResult'] == '>1000') {
              $iQuery = $iQuery->where("vl.result > 1000");
            }
        }if(isset($parameters['clinic'] ) && trim($parameters['clinic'])!=''){
            $iQuery = $iQuery->where(array("vl.facility_id ='".base64_decode($parameters['clinic'])."'")); 
        }if(isset($parameters['lab'] ) && trim($parameters['lab'])!=''){
            $iQuery = $iQuery->where(array("vl.lab_id ='".base64_decode($parameters['lab'])."'")); 
        }if(isset($parameters['sampleType']) && trim($parameters['sampleType'])!=''){
            $iQuery = $iQuery->where('vl.sample_type="'.base64_decode(trim($parameters['sampleType'])).'"');
        }if(isset($parameters['currentRegimen']) && trim($parameters['currentRegimen'])!=''){
            $iQuery = $iQuery->where('vl.current_regimen="'.base64_decode(trim($parameters['currentRegimen'])).'"');
        }if(isset($parameters['adherence']) && trim($parameters['adherence'])!=''){
            $iQuery = $iQuery->where(array("vl.arv_adherance_percentage ='".$parameters['adherence']."'")); 
        }if(isset($parameters['age']) && $parameters['age']!=''){
            if($parameters['age'] == '<18'){
              $iQuery = $iQuery->where("vl.patient_age_in_years < 18");
            }else if($parameters['age'] == '>18') {
              $iQuery = $iQuery->where("vl.patient_age_in_years > 18");
            }else if($parameters['age'] == 'unknown'){
              $iQuery = $iQuery->where("vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL");
            }
        }
        $iQueryStr = $sql->getSqlStringForSqlObject($iQuery);
        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);
        
        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );
        
        foreach ($rResult as $aRow) {
            $row = array();
            $sampleCollectionDate = '';
	    if(isset($aRow['sampleCollectionDate']) && trim($aRow['sampleCollectionDate'])!="" && $aRow['sampleCollectionDate']!= '0000-00-00'){
                $sampleCollectionDate = $common->humanDateFormat($aRow['sampleCollectionDate']);
            }
            $row[] = $sampleCollectionDate;
            $row[] = $aRow['samples'];
            $row[] = ucwords($aRow['sample_name']);
            $row[] = ucwords($aRow['facility_name']);
            $output['aaData'][] = $row;
        }
       return $output;
    }
}
