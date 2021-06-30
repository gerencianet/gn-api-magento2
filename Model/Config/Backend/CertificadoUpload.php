<?php

namespace Gerencianet\Magento2\Model\Config\Backend;

use Exception;
use Magento\Framework\Registry;
use Magento\Framework\Filesystem;
use Magento\Framework\Model\Context;
use Gerencianet\Magento2\Helper\Data;
use Magento\Config\Model\Config\Backend\File;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Config\Model\Config\Backend\File\RequestData\RequestDataInterface;
use Magento\Framework\Filesystem\DirectoryList;

class CertificadoUpload extends File
{

    /** @var Data */
    private $_gHelper;

    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        UploaderFactory $uploaderFactory,
        RequestDataInterface $requestData,
        Filesystem $filesystem,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        Data $gHelper,
        DirectoryList $dl
    ) {

        $this->_gHelper = $gHelper;
        $this->_dir = $dl;

        parent::__construct(
            $context,
            $registry,
            $config,
            $cacheTypeList,
            $uploaderFactory,
            $requestData,
            $filesystem,
            $resource,
            $resourceCollection
        );
    }

    /**
     * Save uploaded file before saving config value
     *
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave() {

        $name = "certificate.pem";
        if ($this->_gHelper->isPixActive()) {
            $value = $this->getValue();
            $file = $this->getFileData();
            $uploadDir = $this->_dir->getPath('media') . "/test/";
            $fileName = isset($value['name']) ? $value['name'] : "";

            if ($this->isInsert($file)) {

                $extName = $this->getExtensionName($fileName);
                if ($this->isValidExtension($extName)) {
                    throw new Exception("Problema ao gravar esta configuração: Extensão Inválida! $extName", 1);
                }

                $this->makeUpload($file, $uploadDir);
                $this->convertToPem($fileName, $uploadDir, $name);
            }
            $this->removeUnusedCertificates($uploadDir);
        }
        $this->setValue($name);
    }

    public function makeUpload($file, $uploadDir) {
        try {
            $uploader = $this->_uploaderFactory->create(['fileId' => $file]);
            $uploader->setAllowedExtensions($this->_getAllowedExtensions());
            $uploader->setAllowRenameFiles(true);
            $uploader->addValidateCallback('size', $this, 'validateMaxSize');
            $uploader->save($uploadDir);
        } catch (Exception $e) {
            throw new LocalizedException(__('%1', $e->getMessage()));
        }
    }

    public function convertToPem($fileName, $uploadDir, $newFilename) {
        $certificate = array();
        $pkcs12 = file_get_contents($uploadDir . $fileName);

        if ($this->getExtensionName($fileName) == "p12") {
            if (openssl_pkcs12_read($pkcs12, $certificate, '')) {
                $pem = $cert = $extracert1 = $extracert2 =  null;

                if (isset($certificate['pkey'])) {
                    openssl_pkey_export($certificate['pkey'], $pem, null);
                }
                
                if (isset($certificate['cert'])) {
                    openssl_x509_export($certificate['cert'], $cert);
                }

                if (isset($certificate['extracerts'][0])) {
                    openssl_x509_export($certificate['extracerts'][0], $extracert1);
                }
                
                if (isset($certificate['extracerts'][1])) {
                    openssl_x509_export($certificate['extracerts'][1], $extracert2);
                }

                $pem_file_contents = $cert . $pem . $extracert1 . $extracert2;
                file_put_contents($uploadDir . $newFilename, $pem_file_contents);
            }
        } else {
            file_put_contents($uploadDir . $newFilename, $pkcs12);
        }
    }

    public function getAllowedExtensions(): array { return ['pem', 'p12']; }

    public function isInsert($file): bool { return (!empty($file)); }

    public function getExtensionName($fileName): string {
        $extNames = explode('.', $fileName);
        return $extNames[count($extNames) - 1];
    }

    public function isValidExtension($extName): bool {
        return !empty($extName) && !in_array($extName, $this->getAllowedExtensions());
    }

    public function isCertificadoInserido($file, $fileName): bool {
        return !empty($file) && !empty($fileName);
    }

    public function removeUnusedCertificates($uploadDir) {
        $files = array_diff(scandir($uploadDir), array('.', '..', 'certificate.pem'));

        foreach ($files as $f) {
            if (is_file("$uploadDir/$f")) {
                unlink("$uploadDir/$f");
            }
        }
    }
}
