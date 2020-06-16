<?php

/* @var $this yii\web\View */

use yii\bootstrap4\ActiveForm;
use yii\bootstrap4\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'Proyecto Juntar';
?>
<div class="site-index">

    <div class="body-content">
        <header class="hero gradient-hero">
            <div class="center-content">
                <?= Html::img('images/juntar-logo-b.svg',  ['class' => 'img-fluid']); ?>
                <br>
                <h5 class="text-white text-uppercase">Sistema Gestión de Eventos</h5>
                <br>
                <a href="#events" class="btn btn-primary btn-lg text-uppercase">Empezar</a>
            </div>
        </header>
        <section class="darkish_bg" id="events">
            <div class="container padding_select">
                <form action="#events">
                    <div class="form-group row">

                        <div class="col-sm-12 col-md-4 mb-3">
                            <select name="orden" class="custom-select custom-select-lg" onchange="this.form.submit()">
                                <option <?= (isset($_GET["orden"]) && $_GET["orden"] == 0) ? "selected" : "" ?> value="0">Fecha de creación</option>
                                <option <?= (isset($_GET["orden"]) && $_GET["orden"] == 0) ? "selected" : "" ?> value="1">Fecha de inicio del evento</option>
                            </select>
                        </div>

                        <div class="col-sm-12 col-md-4 mb-3">
                            <input class="form-control-lg full_width" type="search" placeholder="Buscar" name="s" value="<?= isset($_GET["s"]) ? $_GET["s"] : "" ?>">
                        </div>

                        <div class="col-sm-12 col-md-2 mb-3">
                            <button class="btn btn-outline-success btn-lg full_width" type="submit">Buscar</button>
                        </div>
                        <div class="col-sm-12 col-md-2 mb-3">
                            <?= Html::a('Restablecer', ["index#events"], ['class' => 'btn btn-secondary btn-lg full_width']); ?>
                        </div>

                    </div>
                </form>
            </div>
        </section>
        <section class="dark_bg">
            <div class="container padding_section">
                <?php if (count($eventos) != 0): ?>
            <h2 class="text-white text-uppercase">Últimos Lanzamientos</h2><br>
                <div class="row">
                    <?php foreach ($eventos as $evento): ?>
                        <div class='col-12 col-md-4'>
                        <div class='card bg-light'>
                        <?= Html::img(Url::base('').'/'. Html::encode($evento["imgLogo"]), ["class" => "card-img-top"]) ?>
                            <div class='card-body'>
                                <h5 class='card-title'><?= Html::encode($evento["nombreEvento"]) ?></h5>
                                <h5 class='card-title'><?= Html::encode($evento["fechaInicioEvento"]) ?></h5>
                                <hr>
                                <p class='card-text'><?= Html::encode($evento["lugar"]) ?></p>
                                <p class='card-text'><?= Html::encode(strtok(wordwrap($evento["descripcionEvento"], 100, "...\n"), "\n")) ?> </p>
                                <?= Html::a('Más Información', ['/evento/ver-evento', "idEvento" => Html::encode($evento["idEvento"])], ['class' => 'btn btn-primary btn-lg full_width']); ?>
                                </div>
                        </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="row">
                    <?= // display pagination
                         LinkPager::widget([
                        'pagination' => $pages,
                        ]); 
                    ?>
        
                </div>

                <?php else: ?>
                <div class="row">
                    <h2 class="text-white text-uppercase">No se encontraron eventos, vuelva a intentar.</h2><br>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>