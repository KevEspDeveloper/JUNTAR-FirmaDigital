<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model frontend\models\Evento */
/* @var $form yii\widgets\ActiveForm */
?>
<div class="row">
    <div class="col-md-12 col-12 m-auto">
        <h2 class="text-center">Eventos que posee cargados</h2>
        <p class="text-center">A continuación posee una lista con todos sus eventos cargados</p>

        <table class="table">
            <tbody>
                <tr>
                    <th scope="col">Nombre del evento</th>
                    <td> </td>
                <tr>
                    <?php foreach ($model as $objEvento) : ?>
                <tr>
                    <td><?= $objEvento->nombreEvento ?></td>
                    <td>
                         <?= Html::a('Ver evento', ['ver-evento', 'idEvento' => $objEvento->idEvento], ['class' => 'btn btn-outline-success btn-sm']) ?>
                         <?= Html::a('Editar evento ', ['editar-evento', 'idEvento' => $objEvento->idEvento], ['class' => 'btn btn-outline-success btn-sm']) ?>
                         <?= Html::a('Cargar presentaciones ', ['presentacion/cargar-presentacion', 'idEvento' => $objEvento->idEvento], ['class' => 'btn btn-outline-success btn-sm']) ?>
                         <?php 
                             if(($objEvento->idEstadoEvento) == 4){
                         ?>     
                            <?= Html::a('Publicar evento ', ['publicar-evento', 'idEvento' => $objEvento->idEvento], ['class' => 'btn btn-outline-success btn-sm']) ?>
                         <?php }?>
                         <?php 
                             if(($objEvento->idEstadoEvento) == 1){
                         ?>  
                           <?= Html::a('Despublicar evento ', ['despublicar-evento', 'idEvento' => $objEvento->idEvento], ['class' => 'btn btn-outline-danger btn-sm']) ?>
                         <?php }?>

                    </td>
                <tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>