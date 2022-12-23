<?php

namespace Gerencianet\Magento2\Block\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\File as MageFile;
use Magento\Framework\Filesystem\DirectoryList;

class File extends MageFile {

    /**
     * @param \Magento\Framework\Data\Form\Element\Factory $factoryElement
     * @param \Magento\Framework\Data\Form\Element\CollectionFactory $factoryCollection
     * @param \Magento\Framework\Escaper $escaper
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Data\Form\Element\Factory $factoryElement,
        \Magento\Framework\Data\Form\Element\CollectionFactory $factoryCollection,
        \Magento\Framework\Escaper $escaper,
        array $data = null,
        DirectoryList $dl
    ) {
        if ($data == null) {
            $data = [];
          }
        parent::__construct($factoryElement, $factoryCollection, $escaper, $data);
        $this->_dir = $dl;
    }

    protected function _getDeleteCheckbox() {
        $html = '';
        $nomeArquivo = (string)$this->getValue();

        $filepath = $this->_dir->getPath("media") . "/test/certificate.pem";

        if (file_exists($filepath) && $nomeArquivo) {
            $color = '#006400';
            $html .= '<div>' . "<span style='color:". $color . "'>Há um certificado salvo: ". $nomeArquivo ."<span>" . '</div>';
        } else {
            $color = '#8b0000';
            $html .= '<div>' . "<span style='color:". $color . "'>Você não possui um certificado!<span>" . '</div>';
        }

        return $html;
    }

}
