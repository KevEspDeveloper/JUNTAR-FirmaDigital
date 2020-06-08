<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\Evento */

$this->title = $model->nombreEvento;
$this->params['breadcrumbs'][] = ['label' => 'Eventos', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="evento-view container">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?php
        if (!Yii::$app->user->can('Administrador')) {
            Html::a('Update', ['update', 'id' => $model->idEvento], ['class' => 'btn btn-primary']);
            Html::a('Delete', ['delete', 'id' => $model->idEvento], [
                'class' => 'btn btn-danger',
                'data' => [
                    'confirm' => 'Are you sure you want to delete this item?',
                    'method' => 'post',
                ],
            ]);
        } ?>
        <?PHP
        if (!$yaInscripto) {
            if ($model->preInscripcion == 1 && $model->fechaLimiteInscripcion >= date("Y-m-d")) {
                echo Html::a('Pre-inscribirse', ['inscripcion/preinscripcion', 'id' => $model->idEvento], ['class' => 'btn btn-primary']);
            } else if ($model->preInscripcion == 0) {
                echo Html::a('Inscribirse', ['inscripcion/preinscripcion', 'id' => $model->idEvento], ['class' => 'btn btn-primary']);
            }
        } else {
            if ($acreditacion != 1) {
                echo Html::a('Desinscribirse', ['inscripcion/eliminar-inscripcion', 'id' => $model->idEvento], ['class' => 'btn btn-primary']);
            }
        }
        if ($fechaEvento->fecha <= date("Y-m-d") && $yaInscripto && $acreditacion != 1 && $model->codigoAcreditacion != null) {
            echo Html::a('Acreditación', ['acreditacion/acreditacion', 'id' => $model->idEvento], ['class' => 'btn btn-primary']);
        } else if ($acreditacion == 1) {
            echo Html::label("Usted ya se acredito en este evento");
        }
        ?>

    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'idEvento',
            'idUsuario',
            'nombreEvento',
            'descripcionEvento',
            'lugar',
            'modalidad',
            'linkPresentaciones',
            'linkFlyer',
            'linkLogo',
            'capacidad',
            'preInscripcion',
            'fechaLimiteInscripcion',
            //'fechaDeCreacion',
            //'codigoAcreditacion',
        ],
    ]) ?>

</div>