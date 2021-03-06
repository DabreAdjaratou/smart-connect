<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class VlRequestController extends AbstractActionController
{

    public function indexAction()
    {
        
        //$this->layout()->setVariable('activeTab', 'vl-request');
    }
    public function importFileAction(){
        $this->layout()->setVariable('activeTab', 'vl-request');
        $this->layout()->setVariable('activeMenu', 'vl-request');
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();            
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $sampleService->uploadSampleResultFile($params);
            return $this->redirect()->toRoute("vl-request");
        }else{
            $sourceService = $this->getServiceLocator()->get('SourceService');
            $sourceResult=$sourceService->getAllActiveSource();
            if ($sourceResult) {
                return new ViewModel(array(
                    'sourceResult' => $sourceResult
                ));
            }
        }
    }
}

