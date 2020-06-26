<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model frontend\models\Presentacion */

$this->title = $model->tituloPresentacion;
\yii\web\YiiAsset::register($this);
?>
<div class="container">
<div class="presentacion-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            //'idPresentacion',
            //'idEvento',
			[
				'attribute'=>'Nombre',
				'value'=>$model->idEvento0->nombreEvento,
			],
            //'tituloPresentacion',
            //'descripcionPresentacion',
			[
				'attribute'=>'Descripción',
				'value'=>$model->descripcionPresentacion,
			],
            //'diaPresentacion',
			[
				'attribute'=>'Día',
				'value'=>date('d/m/Y', strtotime($model->diaPresentacion)),
			],
            //'horaInicioPresentacion',
			[
				'attribute'=>'Hora inicio',
				'value'=>date('H:i', strtotime($model->horaInicioPresentacion)),
			],
            //'horaFinPresentacion',
			[
				'attribute'=>'Hora fin',
				'value'=>date('H:i', strtotime($model->horaFinPresentacion)),
			],
            'linkARecursos',
        ],
    ]) ?>

</div>
</div>
