<?php

namespace Application\Service;

use Zend\Session\Container;

class FacilityService {

    public $sm = null;

    public function __construct($sm) {
        $this->sm = $sm;
    }

    public function getServiceManager() {
        return $this->sm;
    }
    
    public function addFacility($params) {
        $adapter = $this->sm->get('Zend\Db\Adapter\Adapter')->getDriver()->getConnection();
        $adapter->beginTransaction();
        try {
            $db = $this->sm->get('FacilityTable');
            $result = $db->addFacility($params);
            if($result>0){
             $adapter->commit();
             $alertContainer = new Container('alert');
             $alertContainer->alertMsg = 'Facility details added successfully';
            }
        }
        catch (Exception $exc) {
            $adapter->rollBack();
            error_log($exc->getMessage());
            error_log($exc->getTraceAsString());
        }
    }
    
    public function updateFacility($params) {
        $adapter = $this->sm->get('Zend\Db\Adapter\Adapter')->getDriver()->getConnection();
        $adapter->beginTransaction();
        try {
            $db = $this->sm->get('FacilityTable');
            $result = $db->updateFacility($params);
            if($result>0){
             $adapter->commit();
             $alertContainer = new Container('alert');
             $alertContainer->alertMsg = 'Facility details updated successfully';
            }
        }
        catch (Exception $exc) {
            $adapter->rollBack();
            error_log($exc->getMessage());
            error_log($exc->getTraceAsString());
        }
    }
   
    public function getFacility($facilityId) {
        $db = $this->sm->get('FacilityTable');
        return $db->fetchFacility($facilityId);
    }
    
    public function getAllFacility($parameters){
        $db = $this->sm->get('FacilityTable');
        return $db->fetchAllFacility($parameters);
    }
    public function fetchFacilityType(){
        $db = $this->sm->get('FacilityTypeTable');
        return $db->fetchFacility();
    }
    public function fetchLocationDetails(){
        $db = $this->sm->get('LocationDetailsTable');
        return $db->fetchLocationDetails();
    }
    public function getDistrictList($locationId)
    {
        $db = $this->sm->get('LocationDetailsTable');
        return $db->fetchDistrictList($locationId);
    }
}