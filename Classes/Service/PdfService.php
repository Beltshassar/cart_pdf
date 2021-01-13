<?php

namespace Extcode\CartPdf\Service;

class PdfService
{
    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManager
     */
    protected $configurationManager;

    /**
     * @var \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager
     */
    protected $persistenceManager;

    /**
     * @var array
     */
    protected $pluginSettings;

    /**
     * @var array
     */
    protected $cartSettings;

    /**
     * @var array
     */
    protected $pdfSettings;

    /**
     * @var \TYPO3\CMS\Core\Resource\ResourceFactory
     * @inject
     */
    protected $resourceFactory;

    /**
     * @var \Extcode\Cart\Domain\Repository\Order\ItemRepository
     */
    protected $itemRepository;

    /**
     * @var \Extcode\CartPdf\Domain\Model\Dto\PdfDemand
     */
    protected $pdfDemand;

    /**
     * @var \Extcode\TCPDF\Service\TsTCPDF
     */
    protected $pdf;

    /**
     * PDF Path
     *
     * @var string
     */
    protected $pdf_path = '/var/tmp/';

    /**
     * @var string
     */
    protected $pdf_filename = 'tempfile.pdf';

    /**
     * @var int
     */
    protected $border = 1;

    /**
     * @param \TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager
     */
    public function injectObjectManager(
        \TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager
    ) {
        $this->objectManager = $objectManager;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface $configurationManager
     */
    public function injectConfigurationManager(
        \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface $configurationManager
    ) {
        $this->configurationManager = $configurationManager;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager $persistenceManager
     */
    public function injectPersistenceManager(
        \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager $persistenceManager
    ) {
        $this->persistenceManager = $persistenceManager;
    }

    /**
     * @param \Extcode\Cart\Domain\Repository\Order\ItemRepository $itemRepository
     */
    public function injectItemRepository(
        \Extcode\Cart\Domain\Repository\Order\ItemRepository $itemRepository
    ) {
        $this->itemRepository = $itemRepository;
    }

    /**
     * @param \Extcode\Cart\Domain\Model\Order\Item $orderItem
     * @param string $pdfType
     */
    public function createPdf(\Extcode\Cart\Domain\Model\Order\Item $orderItem, $pdfType)
    {
        $this->setPluginSettings($pdfType);

        //$pdfFilename = '/tmp/tempfile.pdf';
        
        //change of pdf location
        $pdfFilename = $this->pdf_path.$this->pdf_filename;

        $this->renderPdf($pdfType, $orderItem);

        $storageRepository = $this->objectManager->get(
            \TYPO3\CMS\Core\Resource\StorageRepository::class
        );

        $getNumber = 'get' . ucfirst($pdfType) . 'Number';

        // $newFileName = $orderItem->$getNumber() . '.pdf';

        $better_token = md5(uniqid(mt_rand(), true));
        if ($this->pdfSettings['useFilenameSuffix'] == 1) {
            $suffix = '_' . $better_token;
        }
        $newFileName = $orderItem->$getNumber() . $suffix . '.pdf';

        if (file_exists($pdfFilename)) {
            /** @var \TYPO3\CMS\Core\Resource\ResourceStorage $storage */
            $storage = $storageRepository->findByUid($this->pdfSettings['storageRepository']);
            $targetFolder = $storage->getFolder($this->pdfSettings['storageFolder']);

            if (class_exists('\TYPO3\CMS\Core\Resource\DuplicationBehavior')) {
                $conflictMode = \TYPO3\CMS\Core\Resource\DuplicationBehavior::RENAME;
            } else {
                $conflictMode = 'changeName';
            }

            $falFile = $targetFolder->addFile(
                $pdfFilename,
                $newFileName,
                $conflictMode
            );

            $falFileReference = $this->createFileReferenceFromFalFileObject($falFile);

            $addPdfFunction = 'add' . ucfirst($pdfType) . 'Pdf';
            $orderItem->$addPdfFunction($falFileReference);
        }

        $this->itemRepository->update($orderItem);
        // Not neccessary since 6.2
        $this->persistenceManager->persistAll();
    }

    /**
     * @param string $pdfType
     * @param \Extcode\Cart\Domain\Model\Order\Item $orderItem
     */
    protected function renderPdf($pdfType, $orderItem)
    {
        $pluginSettings = $this->configurationManager->getConfiguration(
            \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
            'cartpdf'
        );

        $this->pdf = $this->objectManager->get(
            \Extcode\TCPDF\Service\TsTCPDF::class
        );
        $this->pdf->setSettings($pluginSettings);
        $this->pdf->setCartPdfType($pdfType . 'Pdf');

        if (!$this->pdfSettings['header']) {
            $this->pdf->setPrintHeader(false);
        } else {
            if ($this->pdfSettings['header']['margin']) {
                $this->pdf->setHeaderMargin($this->pdfSettings['header']['margin']);
                $this->pdf->SetMargins(PDF_MARGIN_LEFT, $this->pdfSettings['header']['margin'], PDF_MARGIN_RIGHT);
            } else {
                $this->pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
            }
        }
        if (!$this->pdfSettings['footer']) {
            $this->pdf->setPrintFooter(false);
        } else {
            if ($this->pdfSettings['footer']['margin']) {
                $this->pdf->setFooterMargin($this->pdfSettings['footer']['margin']);
                $this->pdf->setAutoPageBreak(true, $this->pdfSettings['footer']['margin']);
            } else {
                $this->pdf->setAutoPageBreak(true, PDF_MARGIN_BOTTOM);
            }
        }

        $this->pdf->AddPage();

        $font = 'Helvetica';
        if ($this->pdfSettings['font']) {
            $font = $this->pdfSettings['font'];
        }

        $fontStyle = '';
        if ($this->pdfSettings['fontStyle']) {
            $fontStyle = $this->pdfSettings['fontStyle'];
        }

        $fontSize = 8;
        if ($this->pdfSettings['fontSize']) {
            $fontSize = $this->pdfSettings['fontSize'];
        }

        $this->pdf->SetFont($font, $fontStyle, $fontSize);

        $colorArray = [0, 0, 0];
        if ($this->pdfSettings['drawColor']) {
            $colorArray = explode(',', $this->pdfSettings['drawColor']);
        }
        $this->pdf->setDrawColorArray($colorArray);

        $this->renderMarker();

        if ($this->pdfSettings['letterhead']['html']) {
            foreach ($this->pdfSettings['letterhead']['html'] as $partName => $partConfig) {
                $templatePath = '/' . ucfirst($pdfType) . 'Pdf/Letterhead/';
                $assignToView = ['orderItem' => $orderItem];
                $this->pdf->renderStandaloneView($templatePath, $partName, $partConfig, $assignToView);
            }
        }

        if ($this->pdfSettings['body']['before']['html']) {
            foreach ($this->pdfSettings['body']['before']['html'] as $partName => $partConfig) {
                $templatePath = '/' . ucfirst($pdfType) . 'Pdf/Body/Before/';
                $assignToView = ['orderItem' => $orderItem];
                $this->pdf->renderStandaloneView($templatePath, $partName, $partConfig, $assignToView);
            }
        }

        $this->renderCart($pdfType, $orderItem);

        if ($this->pdfSettings['body']['after']['html']) {
            foreach ($this->pdfSettings['body']['after']['html'] as $partName => $partConfig) {
                $templatePath = '/' . ucfirst($pdfType) . 'Pdf/Body/After/';
                $assignToView = ['orderItem' => $orderItem];
                $this->pdf->renderStandaloneView($templatePath, $partName, $partConfig, $assignToView);
            }
        }

        //$pdfFilename = '/tmp/tempfile.pdf';
        //change of pdf location
        $pdfFilename = $this->pdf_path.$this->pdf_filename;


        $this->pdf->Output($pdfFilename, 'F');
    }

    /**
     *
     */
    protected function renderMarker()
    {
        if ($this->pdfDemand->getFoldMarksEnabled()) {
            $this->pdf->SetLineWidth(0.1);
            $this->pdf->Line(6.0, 105.0, 8.0, 105.0);
            $this->pdf->Line(6.0, 148.5, 10.0, 148.5);
            $this->pdf->Line(6.0, 210.0, 8.0, 210.0);
            $this->pdf->SetLineWidth(0.2);
        }

        if ($this->pdfDemand->getAddressFieldMarksEnabled()) {
            $this->pdf->SetLineWidth(0.1);

            $this->pdf->Line(20.0, 45.0, 21.0, 45.0);
            $this->pdf->Line(20.0, 45.0, 20.0, 46.0);
            $this->pdf->Line(105.0, 45.0, 104.0, 45.0);
            $this->pdf->Line(105.0, 45.0, 105.0, 46.0);

            $this->pdf->Line(20.0, 90.0, 21.0, 90.0);
            $this->pdf->Line(20.0, 90.0, 20.0, 89.0);

            $this->pdf->Line(105.0, 90.0, 104.0, 90.0);
            $this->pdf->Line(105.0, 90.0, 105.0, 89.0);

            $this->pdf->SetLineWidth(0.2);
        }
    }

    /**
     * @param string $pdfType
     * @param \Extcode\Cart\Domain\Model\Order\Item $orderItem
     */
    protected function renderCart($pdfType, $orderItem)
    {
        $pdfType .= 'Pdf';

        $config = $this->pdfSettings['body']['order'];
        $config['height'] = 0;

        if (!$config['spacingY'] && !$config['positionY']) {
            $config['spacingY'] = 5;
        }

        $headerOut = $this->renderCartHeader($pdfType, $orderItem);
        $bodyOut = $this->renderCartBody($pdfType, $orderItem);
        $footerOut = $this->renderCartFooter($pdfType, $orderItem);

        $content = '<table cellpadding="3">' . $headerOut . $bodyOut . $footerOut . '</table>';

        $this->pdf->writeHtmlCellWithConfig($content, $config);
    }

    /**
     * Render Cart Header
     *
     * @param string $pdfType
     * @param \Extcode\Cart\Domain\Model\Order\Item $orderItem
     *
     * @return string
     */
    protected function renderCartHeader($pdfType, $orderItem)
    {
        $view = $this->pdf->getStandaloneView('/' . ucfirst($pdfType) . '/Order/', 'Header');
        $view->assign('orderItem', $orderItem);
        $header = $view->render();
        $headerOut = trim(preg_replace('~[\n]+~', '', $header));

        return $headerOut;
    }

    /**
     * Render Cart Body
     *
     * @param string $pdfType
     * @param \Extcode\Cart\Domain\Model\Order\Item $orderItem
     *
     * @return string
     */
    protected function renderCartBody($pdfType, $orderItem)
    {
        $view = $this->pdf->getStandaloneView('/' . ucfirst($pdfType) . '/Order/', 'Product');
        $view->assign('orderItem', $orderItem);

        $bodyOut = '';

        foreach ($orderItem->getProducts() as $product) {
            $config['$positionY'] = $this->pdf->GetY();
            $view->assign('product', $product);
            $product = $view->render();

            $bodyOut .= trim(preg_replace('~[\n]+~', '', $product));
        }

        return $bodyOut;
    }

    /**
     * Render Cart Footer
     *
     * @param string $pdfType
     * @param \Extcode\Cart\Domain\Model\Order\Item $orderItem
     *
     * @return string
     */
    protected function renderCartFooter($pdfType, $orderItem)
    {
        $view = $this->pdf->getStandaloneView('/' . ucfirst($pdfType) . '/Order/', 'Footer');
        $view->assign('orderSettings', $this->pdfSettings['body']['order']);
        $view->assign('orderItem', $orderItem);
        $footer = $view->render();
        $footerOut = trim(preg_replace('~[\n]+~', '', $footer));

        return $footerOut;
    }

    /**
     * @param string $pdfType
     */
    protected function setPluginSettings($pdfType)
    {
        if (TYPO3_MODE === 'BE') {
            $pageId = (int)(\TYPO3\CMS\Core\Utility\GeneralUtility::_GET('id')) ? \TYPO3\CMS\Core\Utility\GeneralUtility::_GET('id') : 1;

            $frameworkConfiguration = $this->configurationManager->getConfiguration(
                \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK
            );
            $persistenceConfiguration = ['persistence' => ['storagePid' => $pageId]];
            $this->configurationManager->setConfiguration(
                array_merge($frameworkConfiguration, $persistenceConfiguration)
            );
        }

        $this->pluginSettings =
            $this->configurationManager->getConfiguration(
                \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
                'CartPdf'
            );

        $this->cartSettings =
            $this->configurationManager->getConfiguration(
                \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
                'Cart'
            );

        $this->pdfSettings = $this->pluginSettings[$pdfType . 'Pdf'];

        $this->pdfDemand = $this->objectManager->get(
            \Extcode\CartPdf\Domain\Model\Dto\PdfDemand::class
        );

        $this->pdfDemand->setFontSize(
            $this->pdfSettings['fontSize']
        );

        $this->pdfDemand->setDebug(
            $this->pdfSettings['debug']
        );

        $this->pdfDemand->setFoldMarksEnabled(
            boolval($this->pdfSettings['enableFoldMarks'])
        );

        $this->pdfDemand->setAddressFieldMarksEnabled(
            boolval($this->pdfSettings['enableAddressFieldMarks'])
        );
    }

    /**
     * @param \TYPO3\CMS\Core\Resource\File $file
     *
     * @return \TYPO3\CMS\Extbase\Domain\Model\FileReference
     */
    protected function createFileReferenceFromFalFileObject(\TYPO3\CMS\Core\Resource\File $file)
    {
        $falFileReference = $this->resourceFactory->createFileReferenceObject(
            [
                'uid_local' => $file->getUid(),
                'uid_foreign' => uniqid('NEW_'),
                'uid' => uniqid('NEW_'),
                'crop' => null,
            ]
        );

        $fileReference = $this->objectManager->get(
            \TYPO3\CMS\Extbase\Domain\Model\FileReference::class
        );

        $fileReference->setOriginalResource($falFileReference);

        return $fileReference;
    }
}
