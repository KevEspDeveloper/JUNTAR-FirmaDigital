<?php

namespace frontend\controllers;

use frontend\models\Pregunta;
use Yii;
use frontend\models\RespuestaFile;
use frontend\models\RespuestaCorta;
use frontend\models\RespuestaLarga;
use frontend\models\RespuestaSearch;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\UploadedFile;

/**
 * RespuestaController implements the CRUD actions for RespuestaFile model.
 */
class RespuestaController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        $behaviors['access'] = [
            //utilizamos el filtro AccessControl
            'class' => AccessControl::className(),
            'rules' => [
                [
                    'allow' => true,
                    'actions' => [
                        "ver-expositores",
                    ],
                    'roles' => ['?'], // <----- guest
                ],
                [
                    'allow' => true,
                    'actions' => [
                        "ver-expositores",
                        "delete"
                    ],
                    'roles' => ['@'], // <----- guest
                ],
                [
                    'allow' => true,
                    'roles' => ['@'],
                    'matchCallback' => function ($rule, $action) {
                        //                        $module = Yii::$app->controller->module->id;
                        $action = Yii::$app->controller->action->id;        //guardamos la accion (vista) que se intenta acceder
                        $controller = Yii::$app->controller->id;            //guardamos el controlador del cual se consulta
                        //                        $route = "$module/$controller/$action";
                        $route = "$controller/$action";                     //generamos la ruta que se busca acceder
                        //                        $post = Yii::$app->request->post();
                        //preguntamos si el usuario tiene los permisos para visitar el sitio
                        //                        if (Yii::$app->user->can($route, ['post' => $post])) {
                        if (Yii::$app->user->can($route)) {
                            //                            return $this->goHome();
                            return true;
                        }
                    }
                ],
            ],
        ];

        return $behaviors;
    }

    /**
     * Lists all RespuestaFile models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new RespuestaSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single RespuestaFile model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new RespuestaFile model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate($id, $id2)
    {
        $pregunta = Pregunta::find()->where(["id" => $id])->one();

        if($pregunta->tipo == 1){
            $model = new RespuestaCorta;
        }elseif ($pregunta->tipo == 2){
            $model = new RespuestaLarga();
        }else{
            $model = new RespuestaFile();
        }

        $model->idpregunta = $id;
        $model->idinscripcion = $id2;

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            if($pregunta->tipo == 3){
                $model->respuesta = UploadedFile::getInstance($model, 'profileImage');
                if ($model->respuesta != null) {
                    if ($model->upload()) {
                        $model->respuesta = (Yii::getAlias("../web/eventos/formularios/archivos/")) . $model->respuesta->baseName . '.' . $model->respuesta->extension;
                    }
                }
            }

            return $this->redirect(Yii::$app->request->referrer);
        }

        if (Yii::$app->request->isAjax) {
            return $this->renderAjax('create', [
                'model' => $model,
                "pregunta" => $pregunta,
            ]);
        } else {
            return $this->render('create', [
                'model' => $model,
                "pregunta" => $pregunta,
            ]);
        }
    }

    /**
     * Updates an existing RespuestaFile model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing RespuestaFile model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the RespuestaFile model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return RespuestaFile the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = RespuestaFile::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
