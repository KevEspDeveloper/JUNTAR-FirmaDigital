<?php

namespace frontend\controllers;

use Da\QrCode\QrCode;
use frontend\components\validateEmail;
use frontend\models\CategoriaEvento;
use frontend\models\Evento;
use frontend\models\FormularioForm;
use frontend\models\Inscripcion;
use frontend\models\InscripcionSearch;
use frontend\models\ModalidadEvento;
use frontend\models\Pregunta;
use frontend\models\PreguntaSearch;
use frontend\models\RespuestaFile;
use frontend\models\Presentacion;
use frontend\models\PresentacionExpositor;
use frontend\models\PresentacionSearch;
use frontend\models\RespuestaSearch;
use frontend\models\Usuario;
use frontend\models\SolicitudAvalEvento;
use frontend\models\UploadFormLogo;     //Para contener la instacion de la imagen logo
use frontend\models\UploadFormFlyer;    //Para contener la instacion de la imagen flyer
use UI\Controls\Label;
use Yii;
use yii\data\ActiveDataProvider;
use yii\data\Pagination;
use yii\filters\AccessControl;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
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
                    'actions' => [
                        "ver-evento",
                        'verificar-solicitud',
                        'confirmar-solicitud',
                    ],
                    'roles' => ['?'], // <----- guest
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

    public function calcularCupos($evento)
    {
        if (!is_null($evento->capacidad)) {
            //Cantidad de inscriptos al evento
            $cantInscriptos = Inscripcion::find()
                ->where(["idEvento" => $evento->idEvento, 'estado' => 1])
                ->count();

            $cupoMaximo = $evento->capacidad;

            if ($cantInscriptos >= $cupoMaximo) {
                $cupos = 0;
            } else {
                $cupos = $cupoMaximo - $cantInscriptos;
            }
            return $cupos;
        } else {
            return null;
        }
    }

    public function obtenerEstadoEventoNoLogin($cupos, $evento)
    {
        if (($evento->fechaLimiteInscripcion != null && $evento->fechaLimiteInscripcion >= date("Y-m-d"))) {
            if ($cupos !== 0 || is_null($cupos)) {
                return $evento->preInscripcion == 0 ? "puedeInscripcion" : "puedePreinscripcion";
            } else {
                return "sinCupos";
            }
        } elseif ($evento->fechaLimiteInscripcion == null && $evento->fechaInicioEvento >= date("Y-m-d")) {
            return $evento->preInscripcion == 0 ? "puedeInscripcion" : "puedePreinscripcion";
        } else {
            return "noInscriptoYFechaLimiteInscripcionPasada";
        }
    }

    public function obtenerEstadoEvento($evento, $yaInscripto = false, $yaAcreditado = false, $cupos, $tipoInscripcion)
    {

        // ¿Ya esta inscripto o no? - Si
        if ($yaInscripto) {
            // ¿El evento ya inicio? - Si
            if ($evento->fechaInicioEvento <= date("Y-m-d")) {
                // ¿El evento tiene codigo de acreditacion? - Si
                if ($evento->codigoAcreditacion != null) {
                    // ¿El usuario ya se acredito en el evento? - Si
                    if ($yaAcreditado != 1) {
                        return "puedeAcreditarse";
                        // El usuario no esta acreditado
                    } else {
                        return "yaAcreditado";
                    }
                    // El evento no tiene codigo de autentifacion y inicio
                } else {
                    return "inscriptoYEventoIniciado";
                }
                // El evento no inicio todavia y el usuario esta inscripto
            } else {
                // Tipo de inscripcion
                if ($tipoInscripcion == "preinscripcion") {
                    return "yaPreinscripto";
                } else {
                    return "yaInscripto";
                }
            }
            // El usuario no esta incripto en el evento
        } else {
            // ¿Hay cupos en el evento? - No
            if ($cupos === 0 && !is_null($cupos)) {
                return "sinCupos";
                // Hay cupos en el evento
            } else {
                // ¿La fecha actual es menor a la fecha limite de inscripcion? - Si
                // ¿El evento tiene pre inscripcion activada? - Si
                if ($evento->preInscripcion == 1) {
                    if ($evento->fechaLimiteInscripcion == null || $evento->fechaLimiteInscripcion == '1969-12-31') {
                        if ($evento->fechaInicioEvento >= date("Y-m-d")) {
                            return "puedeInscripcion";
                        } else {
                            return "noInscriptoYFechaLimiteInscripcionPasada";
                        }
                        // El evento no tiene pre inscripcion
                    } else {
                        if($evento->fechaInicioEvento >= date("Y-m-d")){
                            return "puedeInscripcion";
                        }else{
                            return "noInscriptoYFechaLimiteInscripcionPasada";
                        }
                        // El evento no tiene pre inscripcion
                    }
                }
            }
        }
    }

    public function verificarDueño($model) {
        if (!Yii::$app->user->isGuest && Yii::$app->user->identity->idUsuario == $model->idUsuario0->idUsuario) {
            return true;
        } else {
            return false;
        }
    }

    public function verificarAdministrador($model) {


        if (!Yii::$app->user->isGuest && Yii::$app->user->identity->idUsuario ) {
        $query=new \yii\db\Query();
        $rows= $query->from('usuario_rol')
            ->andWhere(['user_id'=>Yii::$app->user->identity->idUsuario])
            ->andWhere(['item_name'=>'Administrador'])->all();


        if (count($rows)==0) {
            return false ;
         } else {
             return true;
         }
     }else{
        return false ;
     }

   }

    /**
     * Finds the Evento model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Evento the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id = "", $slug = "") {

        if ($id == "") {
            if (($model = Evento::findOne(["nombreCortoEvento" => $slug])) !== null) {
                return $model;
            }
        } elseif ($slug == "") {
            if (($model = Evento::findOne(["idEvento" => $id])) !== null) {
                return $model;
            }
        }

        throw new NotFoundHttpException('La página solicitada no existe.');
    }

    public function actionRespuestasFormulario($slug)
    {
        $evento = $this->findModel("", $slug);

        $cantidadPreguntas = Pregunta::find()->where(["idEvento" => $evento->idEvento])->count();
        $cantidadInscriptos = Inscripcion::find()->where(["idEvento" => $evento->idEvento])
                                                ->andWhere(["=", "estado", 1])
                                                ->andWhere(["=", "acreditacion", 0])->count();


        if ($this->verificarDueño($evento)) {
            $hayPreguntas = false;
            if ($cantidadPreguntas != 0) {
                $hayPreguntas = true;
            }
            $usuariosSearchModel = new InscripcionSearch();
            $usuariosPreinscriptosDataProvider = new ActiveDataProvider([
                'query' => $usuariosSearchModel::find()->where(["idEvento" => $evento->idEvento, "estado" => 0])->andWhere(["<>", "acreditacion", 1]),
                'pagination' => false,
                'sort' => ['attributes' => ['name', 'description']]
            ]);
            $usuariosInscriptosDataProvider = new ActiveDataProvider([
                'query' => $usuariosSearchModel::find()->where(["idEvento" => $evento->idEvento, "estado" => 1])->andWhere(["<>", "acreditacion", 1]),
                'pagination' => false,
                'sort' => ['attributes' => ['name', 'description']]
            ]);
            Url::remember(Url::current(), 'verRespuestas');
            return $this->render('respuestasFormulario',
                ["preinscriptos" => $usuariosPreinscriptosDataProvider,
                    "inscriptos" => $usuariosInscriptosDataProvider,
                    "evento" => $evento, 'cantidadInscriptos'=>$cantidadInscriptos ,
                    "hayPreguntas" => $hayPreguntas]);
        } else {
            if ($this->verificarDueño($evento)) {
                $usuariosInscriptosSearchModel = new InscripcionSearch();
                $usuariosInscriptosDataProvider = new ActiveDataProvider([
                    'query' => $usuariosInscriptosSearchModel::find()->where(["idEvento" => $evento->idEvento])->andWhere(["estado" => 0]),
                    'pagination' => false,
                    'sort' => ['attributes' => ['name', 'description']]
                ]);
                return $this->render('respuestasFormulario',
                    ["inscriptos" => $usuariosInscriptosDataProvider,
                        "evento" => $evento]);
            } else {
                throw new NotFoundHttpException('La página solicitada no existe.');
            }
        }
    }

    /**
     * Se visualiza un formulario para la carga de un nuevo evento desde la vista cargarEvento. Una vez cargado el formulario, se determina si
     * estan cargado los atributos de las instancias modelLogo y modelFlyer para setear ruta y nombre de las imagenes sobre el formulario.
     * Una ves cargado, se visualiza un mensaje de exito desde una vista.
     */
    public function actionCargarEvento() {

        $model = new Evento();
        $modelLogo = new UploadFormLogo();
        $modelFlyer = new UploadFormFlyer();

        $rutaLogo = (Yii::getAlias("@rutaLogo"));
        $rutaFlyer = (Yii::getAlias("@rutaFlyer"));

        $model->idEstadoEvento = 4; //FLag - Por defecto los eventos quedan en estado "Borrador"
        $model->avalado = 0; // Flag - Por defecto
        $model->fechaCreacionEvento = date('Y-m-d'); // Fecha de hoy

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
           
            $modelLogo->imageLogo = UploadedFile::getInstance($modelLogo, 'imageLogo');
            $modelFlyer->imageFlyer = UploadedFile::getInstance($modelFlyer, 'imageFlyer');

            if ($modelLogo->imageLogo != null) {
                if ($modelLogo->upload()) {
                    $model->imgLogo = $rutaLogo . '/' . $modelLogo->imageLogo->baseName . '.' . $modelLogo->imageLogo->extension;
                }
            }
            if ($modelFlyer->imageFlyer != null) {
                if ($modelFlyer->upload()) {
                    $model->imgFlyer = $rutaFlyer . '/' . $modelFlyer->imageFlyer->baseName . '.' . $modelFlyer->imageFlyer->extension;
                }
            }
            //necesita variables, porque sino hace referencia al objeto model y la referencia pierde el valor si crea una nueva instancia
            if ($model->codigoAcreditacion != null) {
                $nombreCortoEvento = $model->nombreCortoEvento;
                $codAcre = $model->codigoAcreditacion;
                $this->actionGenerarQRAcreditacion($codAcre, $nombreCortoEvento);
            }
            $model->save();
            return $this->redirect(['eventos/ver-evento/' . $model->nombreCortoEvento]);
        }
        $categoriasEventos = CategoriaEvento::find()
                ->select(['descripcionCategoria'])
                ->indexBy('idCategoriaEvento')
                ->column();

        $modalidadEvento = modalidadEvento::find()
                ->select(['descripcionModalidad'])
                ->indexBy('idModalidadEvento')
                ->column();
        return $this->render('cargarEvento', ['model' => $model, 'modelLogo' => $modelLogo, 'modelFlyer' => $modelFlyer, 'categoriasEventos' => $categoriasEventos, 'modalidadEvento' => $modalidadEvento]);
    }

    private function actionGenerarQRAcreditacion($codigoAcreditacion, $slug) {
//        $label = (new Label($slug))
        $label = ($slug);
//                ->setFont(__DIR__ . '/../resources/fonts/monsterrat.otf')
//                ->setFontSize(14);

        $qrCode = (new QrCode((Url::base(true).Url::to(['/acreditacion']) . '?slug=' . $slug . '&codigoAcreditacion=' . $codigoAcreditacion)))
                ->useLogo("../web/images/juntar-logo/png/juntar-avatar-bg-b.png")
//                ->useForegroundColor(51, 153, 255)
//                ->useBackgroundColor(200, 220, 210)
//                //white and black (se ve horrendo
//                ->useForegroundColor(255,255,255)
//                ->useBackgroundColor(0,0,0)
                ->useEncoding('UTF-8')
//                ->setErrorCorrectionLevel(ErrorCorrectionLevelInterface::HIGH)
                ->setLogoWidth(40)
                ->setSize(400)
                ->setMargin(5)
                ->setLabel($label);

        $qrCode->writeFile('../web/eventos/images/qrcodes/' . $slug . '.png');
    }

    public function actionMostrarAcreditaciones() {
        if (Yii::$app->request->get('slug')) {
            $slug = Yii::$app->request->get('slug');
            $rutaImagenQR = Url::base(true) . "/eventos/images/qrcodes/".$slug.'.png';
            return $this->render('mostrarAcreditaciones', [
                        'imageQR' => $rutaImagenQR,
                        'slug' => $slug,
            ]);
        } else {
            return $this->goHome();
        }
    }

    public function actionCrearFormularioDinamico($slug) {

        $evento = $this->findModel("", $slug);

        $esDueño = $this->verificarDueño($evento);


        if ($esDueño) {
            Url::remember(Url::current(), "slugEvento");
            $preguntasSearchModel = new PreguntaSearch();
            $preguntasDataProvider = new ActiveDataProvider([
                'query' => $preguntasSearchModel::find()->where(['idEvento' => $evento->idEvento]),
                'pagination' => false,
                'sort' => ['attributes' => ['name', 'description']]
            ]);

            return $this->render('crearFormularioDinamico',
                            ["preguntas" => $preguntasDataProvider,
                                "evento" => $evento]);
        } else {
            throw new NotFoundHttpException('La página solicitada no existe.');
        }
    }

    public function actionResponderFormulario($slug) {

        $evento = $this->findModel("", $slug);
        $inscripcion = Inscripcion::find()->where(["idEvento" => $evento->idEvento, "idUsuario" => Yii::$app->user->identity->idUsuario])
            ->andWhere(["<>", "estado", 1])
            ->andWhere(["<>", "estado", 2])
            ->one();

        if ($inscripcion != null) {
            $preguntas = Pregunta::find()->where(["idEvento" => $evento->idEvento])->all();

            $respuestaYaHechas = [];
            foreach ($preguntas as $pregunta){
                $respuesta = RespuestaSearch::find()->where(["idpregunta" => $pregunta->id, "idinscripcion" => $inscripcion->idInscripcion])->one();
                if($respuesta == null){
                    array_push($respuestaYaHechas, false);
                }else{
                    array_push($respuestaYaHechas, $respuesta);
                }
            }

            return $this->render('responderFormulario',
                            ["preguntas" => $preguntas,
                                "evento" => $evento,
                                "idInscripcion" => $inscripcion->idInscripcion,
                                "respuestaYaHechas" => $respuestaYaHechas]);
        } else {
            return $this->goHome();
        }
    }

    /**
     * Recibe por parámetro un id, se busca esa instancia del event y se obtienen todos las presentaciones que pertenecen a ese evento.
     * Se envia la instancia del evento junto con todas la presentaciones sobre un arreglo.
     */
    public function actionVerEvento($slug, $token = null) {

        $evento = $this->findModel("", $slug);

        $cantidadPreguntas = Pregunta::find()->where(["idevento" => $evento->idEvento])->count();

        $presentacionSearchModel = new PresentacionSearch();

        $presentacionDataProvider = new ActiveDataProvider([
            'query' => $presentacionSearchModel::find()->where(['idEvento' => $evento->idEvento])->orderBy('idPresentacion'),
            'pagination' => false,
            'sort' => ['attributes' => ['name', 'description']]
        ]);
        $presentaciones = Presentacion::find()->where(['idEvento' => $evento->idEvento])->orderBy('idPresentacion')->all();

        if ($evento == null) {
            return $this->goHome();
        }

        $cupos = $this->calcularCupos($evento);

        $yaInscripto = false;
        $yaAcreditado = false;

        if (!Yii::$app->user->getIsGuest()) {

            $inscripcion = Inscripcion::find()
                            ->where(["idUsuario" => Yii::$app->user->identity->idUsuario, "idEvento" => $evento->idEvento])
                            ->andWhere(["!=", "estado", 2])->one();

            if ($inscripcion != null) {
                $yaInscripto = true;
                $tipoInscripcion = $inscripcion->estado == 0 ? "preinscripcion" : "inscripcion";
                $yaAcreditado = $inscripcion->acreditacion == 1;
                $estadoEvento = $this->obtenerEstadoEvento($evento, $yaInscripto, $yaAcreditado, $cupos, $tipoInscripcion);
            } else {
                $estadoEvento = $this->obtenerEstadoEventoNoLogin($cupos, $evento);
            }
        } else {
            $estadoEvento = $this->obtenerEstadoEventoNoLogin($cupos, $evento);
        }

        //$validarEmail = new validateEmail();
        $esFai = $evento->avalado;
        $esDueño = $this->verificarDueño($evento);
        $esAdministrador = $this->verificarAdministrador($evento);

        if ($token != null) {
          if ( SolicitudAvalEvento::findByEventToken($token) != null) {
            $solicitud = true;
          }
        } else {
          $solicitud = false;
        }

        return $this->render('verEvento', [
                    "evento" => $evento,
                    'presentacion' => $presentaciones,
                    'presentacionSearchModel' => $presentacionSearchModel,
                    'presentacionDataProvider' => $presentacionDataProvider,
                    "estadoEventoInscripcion" => $estadoEvento,
                    'cupos' => $cupos,
                    "esFai" => $esFai,
                    "esDueño" => $esDueño,
                    "esAdministrador" => $esAdministrador,
                    "cantidadPreguntas" => $cantidadPreguntas,
                    'verificacionSolicitud' => $solicitud,
        ]);
    }

    /**
     * Recibe por parámetro un id de evento, se buscar y se obtiene la instancia del evento, se visualiza un formulario
     * cargado con los datos del evento permitiendo cambiar esos datos.
     * Una vez reallizado con cambios, se visualiza un mensaje de exito sobre una vista.
     */
    public function actionEditarEvento($slug) {

        $model = $this->findModel("", $slug);

        $modelLogo = new UploadFormLogo();
        $modelFlyer = new UploadFormFlyer();

        $rutaLogo = (Yii::getAlias("@rutaLogo"));
        $rutaFlyer = (Yii::getAlias("@rutaFlyer"));

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            $modelLogo->imageLogo = UploadedFile::getInstance($modelLogo, 'imageLogo');
            $modelFlyer->imageFlyer = UploadedFile::getInstance($modelFlyer, 'imageFlyer');

            if ($modelLogo->imageLogo != null) {
                if ($modelLogo->upload()) {
                    $model->imgLogo = $rutaLogo . '/' . $modelLogo->imageLogo->baseName . '.' . $modelLogo->imageLogo->extension;
                }
            }
            if ($modelFlyer->imageFlyer != null) {
                if ($modelFlyer->upload()) {
                    $model->imgFlyer = $rutaFlyer . '/' . $modelFlyer->imageFlyer->baseName . '.' . $modelFlyer->imageFlyer->extension;
                }
            }
            if ($model->codigoAcreditacion != null) {
                $nombreCortoEvento = $model->nombreCortoEvento;
                $codAcre = $model->codigoAcreditacion;
                $this->actionGenerarQRAcreditacion($codAcre, $nombreCortoEvento);
            }
            $model->save();
            return $this->redirect(['eventos/ver-evento/' . $model->nombreCortoEvento]);
        }
        $categoriasEventos = CategoriaEvento::find()
                ->select(['descripcionCategoria'])
                ->indexBy('idCategoriaEvento')
                ->column();

        $modalidadEvento = modalidadEvento::find()
                ->select(['descripcionModalidad'])
                ->indexBy('idModalidadEvento')
                ->column();

        return $this->render('editarEvento', ['model' => $model, 'modelLogo' => $modelLogo, 'modelFlyer' => $modelFlyer, 'categoriasEventos' => $categoriasEventos, 'modalidadEvento' => $modalidadEvento]);
    }

    /**
     * Recibe por parametro un id de un evento, buscar ese evento y setea en la instancia $model.
     * Cambia en el atributo fechaCreacionEvento y guarda la fecha del dia de hoy, y en el
     * atributo idEstadoEvento por el valor 1.
     */
    public function actionPublicarEvento($slug) {
        $model = $this->findModel("", $slug);
        $model->idEstadoEvento = 1;  //FLag - Estado de evento activo
        $model->save();


        return $this->redirect(['eventos/ver-evento/' . $model->nombreCortoEvento]);
    }

    /**
     * Recibe por parametro un id de un evento, buscar ese evento y setea en la instancia $model.
     * Cambia en el atributo fechaCreacionEvento por null, y en el
     * atributo idEstadoEvento por el valor 4.
     */
    public function actionSuspenderEvento($slug) {
        $model = $this->findModel("", $slug);
        $model->idEstadoEvento = 4;  //Flag  - Estado de evento borrador
        $model->save();


        return $this->redirect(['eventos/ver-evento/' . $model->nombreCortoEvento]);
    }

     public function actionFinalizarEvento($slug){
        $model = $this->findModel("", $slug);
        $model->idEstadoEvento = 3;  //Flag  - Estado de evento finalizado
        $model->save();

        return $this->redirect(['eventos/ver-evento/'. $model->nombreCortoEvento]);
     }

    public function actionCargarExpositor($idPresentacion) {
        $model = new PresentacionExpositor();
        $objPresentacion = Presentacion::findOne($idPresentacion);
        $objEvento = Evento::findOne($objPresentacion->idEvento);

        if ($model->load(Yii::$app->request->post())) {
            $model->idPresentacion = $idPresentacion;
            $model->save();
            return $this->redirect(['eventos/ver-evento/' . $objEvento->nombreCortoEvento]);
        }

        $usuarios = Usuario::find()
                            ->select(["CONCAT(nombre,' ',apellido) as value", "CONCAT(nombre,' ',apellido)  as  label", "idUsuario as idUsuario"])
                            ->asArray()
                            ->all();

        If(Yii::$app->request->isAjax){
			//retorna renderizado para llamado en ajax
			return $this->renderAjax('cargarExpositor', [
            'model' => $model,
            'objetoEvento' => $objEvento,
            'usuarios' => $usuarios,
        ]);
			}else{
				 return $this->render('cargarExpositor', [
				'model' => $model,
				'objetoEvento' => $objEvento,
				'usuarios' => $usuarios,
			]);
		  }
    }

    public function actionListaParticipantes()
    {
        $request = Yii::$app->request;
        $idEvento = $request->get('idEvento');

        $evento = Evento::findOne($idEvento);

        $datosDelEvento['idEvento'] =   $idEvento;
        $datosDelEvento['organizador'] = $evento->idUsuario0->nombre." ".$evento->idUsuario0->apellido;
        $datosDelEvento['inicio'] = $evento->fechaInicioEvento;
        $datosDelEvento['fin'] =  $evento->fechaFinEvento;
        $datosDelEvento['nombre'] = $evento->nombreEvento;
        $datosDelEvento['capacidad']  = $evento->capacidad ;
        $datosDelEvento['lugar']= $evento->lugar;
        $datosDelEvento['modalidad'] = $evento->idModalidadEvento0->descripcionModalidad;
        
        $base = Inscripcion::find();
        $base->innerJoin('usuario', 'usuario.idUsuario=inscripcion.idUsuario');
        $base->select(['user_estado'=>'inscripcion.estado',
                       'user_acreditacion'=>'inscripcion.acreditacion',
                       'user_idInscripcion'=>'inscripcion.idInscripcion',
                       'user_apellido'=>'usuario.apellido',
                       'user_nombre'=> 'usuario.nombre',
                       'user_dni'=>'usuario.dni',
                       'user_pais'=>'usuario.pais',
                       'user_provincia'=>'usuario.provincia',
                       'user_localidad'=>'usuario.localidad',
                       'user_email'=>'usuario.email',
                       'user_fechaPreInscripcion'=>'inscripcion.fechaPreInscripcion',
                       'user_fechaInscripcion'=>'inscripcion.fechaInscripcion']);

        /// 1: preinscripto    2: inscripto     3: anulado    4: acreditado

        $participantes = $base ->where(['inscripcion.idEvento' => $idEvento ])->orderBy('usuario.apellido ASC')->asArray()->all();
        $preguntas= Pregunta::find()->where(['idevento' => $idEvento ])->asArray()->all();


        $listaRepuesta="";

        $listaRepuesta= array();
       
        foreach($participantes as $unParticipante){

            $base = RespuestaFile::find();
            $base->innerJoin('pregunta', 'respuesta.idpregunta=pregunta.id');
            $base->select(['pregunta_tipo'=>'pregunta.tipo','respuesta_user'=>'respuesta']);
            $respuestas= $base->where(['respuesta.idinscripcion' =>$unParticipante['user_idInscripcion'] ])->asArray()->all();

            $listaRepuesta[]= ['unParticipante'=>$unParticipante, 'respuestas'=>$respuestas];
        }
       

       return $this->renderPartial('listaParticipantes',
        ['datosDelEvento' => $datosDelEvento,
         'preguntas' => $preguntas, 'listaRepuesta' => $listaRepuesta]);
    }

    
    public function actionEnviarEmailInscriptos()
    {
        $request = Yii::$app->request;
        $idEvento  = $request->get('idEvento');

        $evento = Evento::findOne(['idEvento' => $idEvento ]);

///        $base->select(['user_email'=>'usuario.email','user_apellido'=>'usuario.apellido','user_nombre'=>'usuario.nombre']);

        $base= Inscripcion::find()->where(["idEvento" =>   $idEvento ]);
        $base->innerJoin('usuario', 'usuario.idUsuario=inscripcion.idUsuario');
        $base->select(['user_email'=>'usuario.email','user_apellido'=>'usuario.apellido','user_nombre'=>'usuario.nombre']);
        $listaInscriptos= $base->andWhere(["=", "inscripcion.estado", 1])->andWhere(["=", "inscripcion.acreditacion", 0])->asArray()->all();

        $emails = array();

        foreach($listaInscriptos as $unInscripto){
              $emails[]= $unInscripto['user_email'];
        }

        Yii::$app->mailer

            ->compose(
                ['html' => 'confirmacionDeInscripcion-html'],
                ['evento' => $evento],
            )
            ->setFrom([Yii::$app->params['supportEmail'] => 'No-reply @ ' . Yii::$app->name])
            ->setTo($emails)
            ->setSubject('Inscripción el Evento: ' .  $evento->nombreEvento)
            ->send();

              Yii::$app->session->setFlash('success', '<h3> ¡Se han enviado los correos a los inscriptos! </h3>');
           
              return $this->redirect(Url::toRoute(["eventos/respuestas-formulario/". $evento->nombreCortoEvento]));
       

    }
    
    
    public function actionRedactarEmail(){
        
        $participantes=[ 1=>'Todos', 2=>'Pre-inscriptos', 3=>'Inscriptos', 4=>'Expositores' ] ;

        $evento = Evento::findOne(['idEvento' =>1]);
        return $this->render('redactarEmail',['model' => $evento,'participantes'=> $participantes]);
    }


    public function actionOrganizarEventos()
    {
        $idUsuario = Yii::$app->user->identity->idUsuario;

        $request = Yii::$app->request;
        $busqueda = $request->get("s", "");
        $estadoEvento = $request->get("estadoEvento", "");


        if ($estadoEvento != "") {
            if ($estadoEvento == 0) {
                $estado = 1; // activo
            }
            if ($estadoEvento == 1) {
                $estado = 4; // suspendido
            }
            if ($estadoEvento == 2) {
                $estado = 3; // finalizado
            }

        }

        if ($estadoEvento != "") {
            $eventos = Evento::find()
                    ->where(["idUsuario" => $idUsuario])
                    ->andwhere(["like", "idEstadoEvento", $estado]);
        } elseif ($busqueda != "") {
            $eventos = Evento::find()
                    ->where(["idUsuario" => $idUsuario])
                    ->andwhere(["like", "nombreEvento", $busqueda]);
        } else {
            $eventos = Evento::find()->where(["idUsuario" => $idUsuario])->andwhere(["idEstadoEvento" => 1]); // por defecto mostrar los eventos propios que son activos
        }

        //Paginación para 6 eventos por pagina
        $countQuery = clone $eventos;
        $pages = new Pagination(['totalCount' => $countQuery->count()]);
        $pages->pageSize = 6;
        //$pages->applyLimit = $countQuery->count();
        $models = $eventos->offset($pages->offset)
                ->limit($pages->limit)
                ->all();

        return $this->render('organizarEventos', ["eventos" => $models, 'pages' => $pages,]);
    }

    /**
     * Recibe por parámetro un token, carga el Evento buscando el token y verificar sin necesidad
     * loguearse el usuario.
     */
    public function actionVerificarSolicitud($token)
    {
      $solicitud = SolicitudAvalEvento::findByEventToken($token);
      if ($solicitud->verifyByToken($token)) {
        Yii::$app->session->setFlash('success','<small>Evento Confirmado</small>');
        return $this->redirect("/eventos/ver-evento/".$solicitud->getEventShortName());
      } else {
        Yii::$app->session->setFlash('error','<small>Se ha producido un error a al confirmar</small>');
        return $this->redirect("/eventos/ver-evento/".$solicitud->getEventShortName());
      }
    }
    /**
     * Recibe por parametro el nombre corto de un evento, buscar ese evento.
     * Envio del Correo para la confirmacion.
     */
    public function actionEnviarSolicitudEvento($evento)
    {
      $evento = $this->findModel(null, $evento);
      $solicitud = New SolicitudAvalEvento($evento);
      $solicitud->sendEmail();
      Yii::$app->session->setFlash('success','<small>Solicitud Enviada</small>');
      return $this->goBack(Yii::$app->request->referrer);
    }

    /**
     * Recibe por parametro el nombre corto de un evento, buscar ese evento y setea en la instancia $evento.
     * Cambia el estado de avalado a 1 y null al atributo eventoToken.
     */
    public function actionConfirmarSolicitud($slug)
    {
      $evento = $this->findModel(null , $slug);
      $evento->avalado = 1;
      $evento->eventoToken = null;
      if ($evento->save()) {
        Yii::$app->session->setFlash('success','<small>Evento Confirmado</small>');
        return $this->redirect('/eventos/ver-evento/'.$slug);
      } else {
        Yii::$app->session->setFlash('error','<small>Se ha producido un error a al confirmar</small>');
        return $this->redirect('/eventos/ver-evento/'.$slug);
      }

    }
    /**
     * Recibe por parametro el nombre corto de un evento, buscar ese evento y setea en la instancia $evento.
     * Cambia el estado de avalado a 3 y null al atributo eventoToken.
     */
    public function actionDenegarSolicitud($slug)
    {
      $evento = $this->findModel(null , $slug);
      $evento->avalado = 3;
      $evento->eventoToken = null;
      if ($evento->save()) {
        Yii::$app->session->setFlash('success','<small>Evento Denegado</small>');
        return $this->redirect('/eventos/ver-evento/'.$slug);
      } else {
        Yii::$app->session->setFlash('error','<small>Se ha producido un error a al confirmar</small>');
        return $this->redirect('/eventos/ver-evento/'.$slug);
      }

    }

}
