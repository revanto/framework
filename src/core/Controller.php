<?php

/**
 * Description of Controller
 *
 * @author Oleg, Aleksander Simonov <asimv@yandex.ru>
 */

class Controller
{
    
    public static $_userInstance = null;
    public static $_upperMenu;
    /**
     * Текущий пользователь
     * @var user 
     */
    protected $_user = null;
    
    /**
     * Требуется ли авторизация
     * @var bool
     */
    protected $_requireAuth = true;    


    protected function _init() 
    {   
        // $this->_getUser();
        // $this->_checkAuthorization();
        
        View::pushVar('_user', $this->_user);
        
        self::$_userInstance = $this->_user;        
    }
    
    protected function _getUser()
    {
        $this->_user = user::_authFromSession();
    }

    protected function _checkAuthorization() {
        if ($this->_requireAuth && $this->_user == null) {
            if (FApp::$_controller != 'auth') {
                Request::_redirect('/auth');
            }
        }
        else if ($this->_requireAuth) {
            if (!SecurityContext::_isControllerAllowed($this->_user, FApp::$_controller)) {
                $this->deniedZone();
            }
        }
    }
    
    public function deniedZone(){
        Request::_redirect('/denied');
    }


    protected function _jsonNotAllowedAction() {
        View::_JSON([
            'error' =>  'У вас нет прав на данное действие'
        ]);
    }
    
    /**
     * 
     * @param string $action
     * @return boolean
     */
    protected function _isActionAllowed($action) {
	return true;
        // return SecurityContext::_actionAllowed($this->_user, FApp::$_controller, $action);
    }
    
    public static function _controllerName() {
        $class = get_called_class();
        $name = str_replace("Controller", '', $class);
        return $name;
    }
    
    
    
    // обработка стандартных операций над формой (CRUD)
    protected static function searchForStandardOperations( $form , $recordId ) {
	
	$formClass = $form . 'Form';
	$formObject = new $formClass($recordId);
	
	// удаление записи
	if ( Request::_POST('delete') ) {
	    return $formObject -> delete();	     // возвращает true 
	}
	
	// изменение или создание записи
	if ( Request::_POST('save') ) {
	    $id = static::saveObject( $formObject );
	    return $id;
	}
	
	return false;
    }
    
    
    protected static function saveObject( $object ) {
	$formData = Request::_POST('save');	
	$object -> changeAll( $formData );	
	$id = $object -> save();	
	return $id;
    }


    // большинство контроллеров делают стандартные действия, вот эти
    protected static function performStandardAction( $modelForm ) {
	$operation = Request::_URL(2);
	$formClass = $modelForm . 'Form';
	switch($operation) {
	    case 'list' : 
		$form = new $formClass();	
		$data = $form -> getReferenceData();
		$data['predefinedFilter'] = self::searchFilterData();
		View::_JSON( $data );
		break;
	    case 'item' :
		$id = (int)Request::_URL(3, 0);
		$actionResult = self::searchForStandardOperations($modelForm, $id);
		if ( $actionResult !== true && $actionResult !== false ) {
		    $id = $actionResult;
		}
		$form = new $formClass( $id );
		if ( !$id ) {
		    $form -> getInitFilterFields();
		}
		$data = $form -> getFormData();
		View::_JSON( $data );
		break;
	}
    }
    
    
    // поиск предустановленных фильтров для справочника
    protected static function searchFilterData() {
	$output = [];
	foreach( $_POST as $key => $value ) {
	    if ( substr( $key, 0, 7 ) === 'filter_' ) {
		$output[ substr( $key, 7 ) ] = $value;
	    }
	}
	return $output;
    }
 
    
    // заливка картинки общая для нескольких контроллеров
    public static function uploadPictureAction() {
	// Logger::log( print_r( debug_backtrace() , true ) );
	foreach( $_FILES as $key => $value ) {
	    $info = explode( '_' , $key );
	    // Logger::log( $info[1] . $info[0] . $info[2] );
	    if ( count( $info ) !== 3 || !$info[0] || !$info[1] ) {
		continue;
	    }	    
	    Picture::upload( $info[1] , $info[0] , $info[2] , $value );
	    if ( strpos( $info[1] , 'big' ) ) {
		Picture::upload( str_replace( 'big' , 'small' , $info[1] ) , $info[0] , $info[2] , $value );
		View::_JSON( getimagesize( $info[0] ) );
	    }
	}	
    }
    
    
     // обработка картинки общая для нескольких контроллеров
    public static function resizePictureAction() {
	switch( \Request::_GET('mode') ) {
	    case 'product' : self::resizeProduct(); break;
	    case 'model' : self::resizeModel(); break;
	}
    }
 
    
    // обработка картинки товара
    private static function resizeProduct() {
	$code = \Car\ProductTable::getField( 'code' , [ 'id' => \Request::_GET('id') ] );
	$prefix = \Request::_GET('size');
	$site = \Request::_GET('site');
	if ( $code && $prefix && $site ) {
	    $inputFile = \Picture::findFile( DOCUMENT_ROOT . '/picture_original/product/' . $site . $prefix , $code );
	    $outputFile = DOCUMENT_ROOT . '/picture/product/' . $site . $prefix . '/' . $code . '.jpg';
	    if ( !$inputFile ) {
		@copy( $outputFile , DOCUMENT_ROOT . '/picture_original/product/' . $site . $prefix . '/' . $code . '.jpg' );
		$inputFile = \Picture::findFile( DOCUMENT_ROOT . '/picture_original/product/' . $site . $prefix , $code );
	    }
	    if ( $inputFile ) {
		\Picture::resize( $inputFile , $outputFile , $site , 'product_' . $prefix );
		View::_JSON( getimagesize( $outputFile ) );
	    } else {
		// View::_JSON( [ 'Ошибка: файл /picture_original/product/' . $site . $prefix . '/'. $code . '.* не найден' ] );
		// View::_JSON( [ DOCUMENT_ROOT . '/picture_original/product/' . $site . $prefix , $code ] );
	    }
	} else {
	    // View::_JSON( [ $code . $prefix , $site ] );
	}
    }
    
    
    // обработка картинки модели
    private static function resizeModel() {
	$url = \Car\ModelTable::getField( 'url' , [ 'id' => \Request::_GET('id') ] );
	$site = \Request::_GET('site');
	if ( $url && $site ) {
	    $inputFile = \Picture::findFile( DOCUMENT_ROOT . '/picture_original/model/' . $site , $url );
	    $outputFile = DOCUMENT_ROOT . '/picture/model/' . $site . '/' . $url . '.jpg';
	    if ( $inputFile ) {
		\Picture::resize( $inputFile , $outputFile , $site , 'model' );
		View::_JSON( getimagesize( $outputFile ) );
		return;
	    }
	}
	View::_JSON( [ 'Что-то пошло не так.' ] );
	return;
    }
    
    
    public static function getMenuArray() {
	return static::$_upperMenu;
    }
}