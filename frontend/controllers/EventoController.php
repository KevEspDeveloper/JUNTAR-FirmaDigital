<?php

namespace frontend\controllers;

use Yii;
use frontend\models\EventoSearch;
use frontend\models\Inscripcion;
use frontend\models\Presentacion;
use frontend\models\PresentacionExpositor;
use frontend\models\Evento;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\helpers\Url;

use frontend\models\UploadFormLogo;     //Para contener la instacion de la imagen logo 
use frontend\models\UploadFormFlyer;    //Para contener la instacion de la imagen flyer
use yii\web\UploadedFile;

/**
 * EventoController implements the CRUD actions for Evento model.
 */
class EventoController extends Controller
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
     * Lists all Evento models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new EventoSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Evento model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        /*
   En caso que se agregue a la tabla Evento el campo 'estado' modificar la consulta Evento::find()..
   en Where filtrar por 'estado' activo.
*/
        $evento = $this->findModel($id);

        if($evento == null){
            return $this->goHome();
        }

        $cupos = $this->calcularCupos($evento);

        $yaInscripto = false;
        $yaAcreditado = false;

        if (!Yii::$app->user->getIsGuest()){

            $inscripcion = Inscripcion::find()
                ->where(["idUsuario" => Yii::$app->user->identity->idUsuario, "idEvento" => $id])
                ->andWhere(["!=", "estado", 2])->one();

            if($inscripcion != null) {
                $yaInscripto = true;
                $tipoInscripcion = $inscripcion->estado == 0 ? "preinscripcion" : "inscripcion";
                $yaAcreditado = $inscripcion->acreditacion == 1;
                $estadoEvento = $this->obtenerEstadoEvento($evento,$yaInscripto,$yaAcreditado, $cupos, $tipoInscripcion);
            }else{
                $estadoEvento = $this->obtenerEstadoEventoNoLogin($cupos,$evento);
            }

            }else{
            $estadoEvento = $this->obtenerEstadoEventoNoLogin($cupos,$evento);
        }
            return $this->render('view', [
                "evento" => $evento,
                "estadoEvento" => $estadoEvento,
                'cupos' => $cupos,
            ]);
    }

    /**
     * Creates a new Evento model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Evento();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->idEvento]);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing Evento model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->idEvento]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing Evento model.
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

    public function calcularCupos($evento){
        //Cantidad de inscriptos al evento
        $cantInscriptos = Inscripcion::find()
            ->where(["idEvento" => $evento->idEvento, 'estado'=>1])
            ->count();

        $cupoMaximo = $evento->capacidad;

        if ($cantInscriptos >= $cupoMaximo) {
            $cupos = 0;
        } else {
            $cupos = $cupoMaximo - $cantInscriptos;
        }

        return $cupos;
    }

    public function obtenerEstadoEventoNoLogin($cupos, $evento){
        if ($cupos != 0){
            return $evento->preInscripcion == 0 ? "puedeInscripcion" : "puedePreinscripcion";
        }else{
            return "sinCupos";
        }
    }

    public function obtenerEstadoEvento($evento, $yaInscripto = false, $yaAcreditado = false, $cupos, $tipoInscripcion){

        // ¿Ya esta inscripto o no? - Si
        if($yaInscripto){
            // ¿El evento ya inicio? - Si
            if($evento->fechaInicioEvento <= date("Y-m-d")){
                // ¿El evento tiene codigo de acreditacion? - Si
                if($evento->codigoAcreditacion != null){
                    // ¿El usuario ya se acredito en el evento? - Si
                    if($yaAcreditado != 1){
                        return "puedeAcreditarse";
                        // El usuario no esta acreditado
                    }else{
                        return "yaAcreditado";
                    }
                    // El evento no tiene codigo de autentifacion y inicio
                }else{
                    return "inscriptoYEventoIniciado";
                }
            // El evento no inicio todavia y el usuario esta inscripto
            }else{
                // Tipo de inscripcion
                if($tipoInscripcion == "preinscripcion"){
                    return "yaPreinscripto";
                }else{
                    return "yaInscripto";
                }
            }
            // El usuario no esta incripto en el evento
        }else{
            // ¿Hay cupos en el evento? - No
            if ($cupos == 0){
                return "sinCupos";
                // Hay cupos en el evento
            }else{
                // ¿La fecha actual es menor a la fecha limite de inscripcion? - Si
                if($evento->fechaLimiteInscripcion >= date("Y-m-d")){
                    // ¿El evento tiene pre inscripcion activada? - Si
                    if($evento->preInscripcion == 1){
                        return "puedePreinscripcion";
                        // El evento no tiene pre inscripcion
                    }else{
                        return "puedeInscripcion";
                    }
                }else{
                    return "noInscriptoYFechaLimiteInscripcionPasada";
                }
            }
        }
    }

    /**
     * Finds the Evento model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Evento the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Evento::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('La página solicitada no existe.');
    }

    /**
     * Se visualiza un formulario para la carga de un nuevo evento desde la vista cargarEvento. Una vez cargado el formulario, se determina si
     * estan cargado los atributos de las instancias modelLogo y modelFlyer para setear ruta y nombre de las imagenes sobre el formulario.
     * Una ves cargado, se visualiza un mensaje de exito desde una vista.
     */
    public function actionCargarEvento()
    {

        $model = new Evento();
        $modelLogo = new UploadFormLogo();
        $modelFlyer = new UploadFormFlyer();        

        $rutaLogo = (Yii::getAlias("@rutaLogo"));
        $rutaFlyer = (Yii::getAlias("@rutaFlyer"));

        if ($model->load(Yii::$app->request->post()) ) {
            $model->idEstadoEvento = 4; //FLag - Por defecto los eventos quedan en estado "Borrador"

            $modelLogo->imageLogo = UploadedFile::getInstance($modelLogo, 'imageLogo');
            $modelFlyer->imageFlyer = UploadedFile::getInstance($modelFlyer, 'imageFlyer');

            if($modelLogo->imageLogo != null){
                if($modelLogo->upload()){
                    $model->imgLogo = $rutaLogo . '/' . $modelLogo->imageLogo->baseName . '.' . $modelLogo->imageLogo->extension;
                }
            }    
            if($modelFlyer->imageFlyer != null){
                if($modelFlyer->upload()){
                    $model->imgFlyer = $rutaFlyer . '/' . $modelFlyer->imageFlyer->baseName . '.' . $modelFlyer->imageFlyer->extension;
                 }
            }
            $model->save();
            return $this->redirect(['evento-cargado', 'idEvento' => $model->idEvento]);
  
        }
        return $this->render('cargarEvento', ['model' => $model, 'modelLogo' => $modelLogo, 'modelFlyer' => $modelFlyer]);
    }


    public function actionEventoCargado($idEvento)
    {
        return $this->render('eventoCargado', [
            'model' => $this->findModel($idEvento),
        ]);
    }

    /**
     * Recibe por parámetro un id, se busca esa instancia del event y se obtienen todos las presentaciones que pertenecen a ese evento.
     * Se envia la instancia del evento junto con todas la presentaciones sobre un arreglo.
     */
    public function actionVerEvento($idEvento)
    {

        $evento = $this->findModel($idEvento);
        $presentaciones = Presentacion::find()->where(['idEvento' => $idEvento])->orderBy('idPresentacion')->all();

        if($evento == null){
            return $this->goHome();
        }

        $cupos = $this->calcularCupos($evento);

        $yaInscripto = false;
        $yaAcreditado = false;

        if (!Yii::$app->user->getIsGuest()){

            $inscripcion = Inscripcion::find()
                ->where(["idUsuario" => Yii::$app->user->identity->idUsuario, "idEvento" => $idEvento])
                ->andWhere(["!=", "estado", 2])->one();

            if($inscripcion != null) {
                $yaInscripto = true;
                $tipoInscripcion = $inscripcion->estado == 0 ? "preinscripcion" : "inscripcion";
                $yaAcreditado = $inscripcion->acreditacion == 1;
                $estadoEvento = $this->obtenerEstadoEvento($evento,$yaInscripto,$yaAcreditado, $cupos, $tipoInscripcion);
            }else{
                $estadoEvento = $this->obtenerEstadoEventoNoLogin($cupos,$evento);
            }

        }else{
            $estadoEvento = $this->obtenerEstadoEventoNoLogin($cupos,$evento);
        }
        return $this->render('verEvento', [
            "evento" => $evento,
            'presentacion' => $presentaciones,
            "estadoEventoInscripcion" => $estadoEvento,
            'cupos' => $cupos,
        ]);
    }


    /**
     * Identifica al usuario logueado, obtiene su instancia y busca todos los eventos que pertenezcan a ese usuario
     * Se envia en la vista un arreglo con todos los eventos.   
     */
    public function actionListarEventos()
    {
        $idUsuario = Yii::$app->user->identity->idUsuario;
        $listaEventos = Evento::find()->where(['idUsuario' => $idUsuario])->orderBy('idEvento')->all();
        return $this->render('listarEventos', ['model' => $listaEventos]);
    }


    
     /**
     * Recibe por parámetro un id de evento, se buscar y se obtiene la instancia del evento, se visualiza un formulario 
     * cargado con los datos del evento permitiendo cambiar esos datos.
     * Una vez reallizado con cambios, se visualiza un mensaje de exito sobre una vista.
     */
     public function actionEditarEvento($idEvento){

        $model = $this->findModel($idEvento);

        $modelLogo = new UploadFormLogo();
        $modelFlyer = new UploadFormFlyer();

        $rutaLogo = (Yii::getAlias("@rutaLogo"));
        $rutaFlyer = (Yii::getAlias("@rutaFlyer"));

        if($model->load(Yii::$app->request->post())) {
            $modelLogo->imageLogo = UploadedFile::getInstance($modelLogo, 'imageLogo'); 
            $modelFlyer->imageFlyer = UploadedFile::getInstance($modelFlyer, 'imageFlyer'); 

            if($modelLogo->imageLogo != null){
                if($modelLogo->upload()){
                    $model->imgLogo = $rutaLogo . '/' . $modelLogo->imageLogo->baseName . '.' . $modelLogo->imageLogo->extension;
                }
            }    
            if($modelFlyer->imageFlyer != null){
                if($modelFlyer->upload()){
                    $model->imgFlyer = $rutaFlyer . '/' . $modelFlyer->imageFlyer->baseName . '.' . $modelFlyer->imageFlyer->extension;
                 }
            }
            $model->save();
            return $this->redirect(['ver-evento', 'idEvento' => $model->idEvento]);
        }

        return $this->render('editarEvento', [
            'model' => $model,
            'modelLogo' => $modelLogo, 
            'modelFlyer' => $modelFlyer
        ]);
     }

   
     /**
      * Recibe por parametro un id de un evento, buscar ese evento y setea en la instancia $model.
      * Cambia en el atributo fechaCreacionEvento y guarda la fecha del dia de hoy, y en el
      * atributo idEstadoEvento por el valor 1.
      */
     public function actionPublicarEvento($idEvento){
        $model = $this->findModel($idEvento);
       
        $model->fechaCreacionEvento = date('Y-m-d');    
        $model->idEstadoEvento = 1;  //FLag - Estado de evento activo

        $model->save();
        return $this->render('eventoPublicado', [
            'model' => $model,
            ]);
     }   

     /**
      * Recibe por parametro un id de un evento, buscar ese evento y setea en la instancia $model.
      * Cambia en el atributo fechaCreacionEvento por null, y en el
      * atributo idEstadoEvento por el valor 4.
      */
     public function actionDespublicarEvento($idEvento){
        $model = $this->findModel($idEvento);
       
        $model->fechaCreacionEvento = null;   
        $model->idEstadoEvento = 4;  //Flag  - Estado de evento borrador

        $model->save();
        return $this->render('eventoDespublicado', [
            'model' => $model,
            ]);
     }   
     
     public function actionCargarExpositor($idPresentacion)
    {
        $model = new PresentacionExpositor();
        $objPresentacion = Presentacion::findOne($idPresentacion);
        $objEvento = Evento::findOne($objPresentacion->idEvento);

        if ($model->load(Yii::$app->request->post())) {
            $model->idPresentacion = $idPresentacion;
            $model->save();
            return $this->redirect(['ver-evento', 'idEvento' => $objEvento->idEvento]);
        }

        return $this->render('cargarExpositor', [
            'model' => $model
        ]);
    }

}
