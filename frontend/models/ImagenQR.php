<?php

namespace frontend\models;

use Da\QrCode\QrCode;
use yii\base\Model;
use yii\helpers\Url;
use Yii;

/**
 * ImagenEvento is the model behind the ImagenQR.
 */
class ImagenQR extends Model {

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            ['']
        ];
    }

    private function generarQR($url, $pathImage, $label) {
        $qrCode = (new QrCode($url))
                ->useLogo("../web/images/juntar-logo/png/juntar-avatar-bg-b.png")
                ->useEncoding('UTF-8')
                ->setLogoWidth(45)
                ->setSize(400)
                ->setMargin(5)
                ->setLabel($label);
        return $qrCode->writeFile('../web/' . $pathImage);
    }

    public function generarQREvento($slug, $idEvento) {
        $url = (Url::base(true) . Url::to(['/eventos/ver-evento']) . "/" . $slug);
        $path = 'eventos/images/qrcodes/' . $slug . '.png';
        $label = $slug;
        $imagenGuardada = $this->generarQR($url, $path, $label);

        if ($imagenGuardada) {
            $model = new ImagenEvento();
            return $model->guardarImagen($path, 3, $idEvento);
        } else {
            return false;
        }
    }

    public function updateQREvento($slug, $idEvento) {
        //buscamos la existencia del registro en la DB
        $modelImage = ImagenEvento::findOne(['idEvento' => $idEvento, 'categoriaImagen' => 3]);
        if ($modelImage == null) {
            //no se encontró el registro, se procede a crear el codigo QR
            $this->generarQREvento($slug, $idEvento);
        } else {
            //seteamos los datos necesarios para actualizar el QR
            $url = (Url::base(true) . Url::to(['/eventos/ver-evento']) . "/" . $slug);
            $path = 'eventos/images/qrcodes/' . $slug . '.png';
            $label = $slug;
            
            //se encontró el registro en la DB, procedemos a buscar el archivo en el servidor
            $rutaQREvento = $modelImage->rutaArchivoImagen;
            if (file_exists($rutaQREvento)) {
                //encontro el archivo, procede a eliminarlo del server
                unlink($rutaQREvento);

                //generamos el nuevo QR
                $imagenGuardada = $this->generarQR($url, $path, $label);
                if ($imagenGuardada) {
                    return $modelImage->updateImagen($path);
                } else {
                    return false;
                }
            } else {
                //existe el registro y no existe el archivo
                //generamos el nuevo QR
                $imagenGuardada = $this->generarQR($url, $path, $label);
                if ($imagenGuardada) {
                    return $modelImage->updateImagen($path);
                } else {
                    return false;
                }
            }
        }
    }

    public function generarQRAcreditacion($codigoAcreditacion, $slug, $idEvento) {
        $url = (Url::base(true) . Url::to(['/acreditacion']) . '?slug=' . $slug . '&codigoAcreditacion=' . $codigoAcreditacion);
        $path = "eventos/images/qrcodes/" . Yii::$app->security->generateRandomString() . "-Acreditacion.png";
        $label = $slug;
        $imagenGuardada = $this->generarQR($url, $path, $label);

        if ($imagenGuardada) {
            $model = new ImagenEvento();
            return $model->guardarImagen($path, 4, $idEvento);
        } else {
            return false;
        }
    }

    public function updateQRAcreditacion($codigoAcreditacion, $slug, $idEvento) {
        //buscamos la existencia del registro en la DB
        $modelImage = ImagenEvento::findOne(['idEvento' => $idEvento, 'categoriaImagen' => 4]);
        if ($modelImage == null) {
            //no se encontró el registro, se procede a crear el codigo QR
            return $this->generarQRAcreditacion($codigoAcreditacion, $slug, $idEvento);
        } else {
            //seteamos los datos necesarios para actualizar el QR
            $url = (Url::base(true) . Url::to(['/eventos/ver-evento']) . "/" . $slug);
            $path = 'eventos/images/qrcodes/' . $slug . '.png';
            $label = $slug;

            //se encontró el registro en la DB, procedemos a buscar el archivo en el servidor
            $rutaQREvento = $modelImage->rutaArchivoImagen;
            if (file_exists($rutaQREvento)) {
                //encontro el archivo, procede a eliminarlo del server
                unlink($rutaQREvento);

                //generamos el nuevo QR
                $imagenGuardada = $this->generarQR($url, $path, $label);
                if ($imagenGuardada) {
                    return $modelImage->updateImagen($path);
                } else {
                    return false;
                }
            } else {
                //existe el registro y no existe el archivo
                //generamos el nuevo QR
                $imagenGuardada = $this->generarQR($url, $path, $label);
                if ($imagenGuardada) {
                    return $modelImage->updateImagen($path);
                } else {
                    return false;
                }
            }
        }
        $rutaQREvento = $modelImage->rutaArchivoImagen;
        if (file_exists($rutaQREvento)) {
            //elimina el archivo de la carpeta
            unlink($rutaQREvento);

            $label = ($slug);
            $qrCode = (new QrCode((Url::base(true) . Url::to(['/acreditacion']) . '?slug=' . $slug . '&codigoAcreditacion=' . $codigoAcreditacion)))
                    ->useLogo("../web/images/juntar-logo/png/juntar-avatar-bg-b.png")
                    ->useEncoding('UTF-8')
                    ->setLogoWidth(45)
                    ->setSize(400)
                    ->setMargin(5)
                    ->setLabel($label);

            $path = "eventos/images/qrcodes/" . Yii::$app->security->generateRandomString() . "-Acreditacion.png";
            $insercion = $qrCode->writeFile('../web/' . $path);
            if ($insercion) {
                return $modelImage->updateImagen($path);
            } else {
                return false;
            }
        } else {
            //el archivo no existe, procedemos a generarlo
            return $this->generarQRAcreditacion($codigoAcreditacion, $slug, $idEvento);
        }
    }

    /**
     * {@inheritdoc}
     */
//    public function attributeLabels() {
//        return [
//        ];
//    }
}
