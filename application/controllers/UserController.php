<?php
/**
 * Nolotiro user controller - Handling user related actions
 *
 */

class UserController extends Zend_Controller_Action {

    protected $session = null;
    protected $_model;

    /**
     *
     *
     */
    public function init() {
        parent::init ();
        $this->view->baseUrl = Zend_Controller_Front::getParam ( $route );

        $locale = Zend_Registry::get ( "Zend_Locale" );
        $this->view->lang = $locale->getLanguage ();

        $aNamespace = new Zend_Session_Namespace('Nolotiro');
        $this->location = $aNamespace->location;

    }

    /**
     * Default action - if logged in, go to profile. If logged out, go to register.
     *
     */
    public function indexAction() {
        //by now just redir to /
        $this->_redirect ( '/' );

    }

    /**
     * register - register a new user into the nolotiro database
     */

    public function registerAction() {
        $request = $this->getRequest ();
        $form = $this->_getUserRegisterForm ();

        // check to see if this action has been POST'ed to
        if ($this->getRequest ()->isPost ()) {

            // now check to see if the form submitted exists, and
            // if the values passed in are valid for this form
            if ($form->isValid ( $request->getPost () )) {

                // since we now know the form validated, we can now
                // start integrating that data submitted via the form
                // into our model
                $formulario = $form->getValues ();
                //Zend_Debug::dump($formulario);


                $model = $this->_getModel ();

                //check user email and nick if exists
                $checkemail = $model->checkEmail ( $formulario ['email'] );
                $checkuser = $model->checkUsername ( $formulario ['username'] );

                //not allow to use the email as username
                if ( $formulario['email'] == $formulario['username']) {

                    $view = $this->initView();
                    $view->error = $this->view->translate('You can not use your email as username. Please,
									      choose other username');
                }



                if ($checkemail !== NULL) {
                    $view = $this->initView ();
                    $view->error = $this->view->translate ( 'This email is taken. Please, try again.' );

                }

                if ($checkuser !== NULL) {
                    $view = $this->initView ();
                    $view->error = $this->view->translate ( 'This username is taken. Please, try again.' );

                }

                if ($checkemail == NULL and $checkuser == NULL) {

                    // success: insert the new user on ddbb


                    //update the ddbb with new password
                    $password = $this->_generatePassword ();
                    $data ['password'] = md5 ( $password );
                    $data ['email'] = $formulario ['email'];
                    $data ['username'] = $formulario ['username'];

                    $model->save ( $data );

                    //once token generated by model save, now we need it to send to the user by email
                    $token = $model->getToken($formulario['email']);
                    //Zend_Debug::dump($token);


                    //now lets send the validation token by email to confirm the user email
                    $hostname = 'http://' . $this->getRequest ()->getHttpHost ();

                    $mail = new Zend_Mail ( );
                    $mail->setBodyHtml ( $this->view->translate ( 'Please, click on this url to finish your register process:<br />' )
                            . $hostname . $this->view->translate ( '/en/user/validate/t/' ) . $token .
                            '<br /><br />' . utf8_decode ( $this->view->translate ( 'After validate this link you will be able to access with your email and this password:' ) ) .
                            '<br />' . utf8_decode ( $this->view->translate ( 'Password:' ) ) .'  '.$password );
                    $mail->setFrom ( 'noreply@nolotiro.org', 'nolotiro.org' );

                    $mail->addTo($formulario['email']);
                    $mail->setSubject ( $formulario ['username'] . $this->view->translate ( ', confirm your email' ) );
                    $mail->send ();

                    $this->_helper->_flashMessenger->addMessage ( $this->view->translate ( 'Check your inbox email to finish the register process' ) );

                    $this->_redirect ( '/'.$this->view->lang.'/woeid/'.$this->location.'/give' );


                }

            }
        }
        // assign the form to the view
        $this->view->form = $form;

    }



    public function profileAction() {

        $request = $this->getRequest ();
        $user_id = (int)$this->_request->getParam ( 'id' );

        if ($user_id == null) {
            $this->_helper->_flashMessenger->addMessage ( $this->view->translate ( 'this user does not exist' ) );
            $this->_redirect ( '/'.$this->view->lang.'/ad/list/woeid/'.$this->location.'/ad_type/give' );
        }



        $model = $this->_getModel ();
        $modelarray = $model->fetchUser($user_id);

        //lets overwrite the password and token values to assure not passed to the view ever!
        unset ($modelarray['password']);
        unset ($modelarray['token']);

        $this->view->user = $modelarray;

    }


    /**
     *
     * @return Form_UserRegister
     */
    protected function _getUserRegisterForm() {
        require_once APPLICATION_PATH . '/forms/UserRegister.php';
        $form = new Form_UserRegister ( );
        return $form;
    }

    /**
     * forgot - sends (regenerates) a new token to the user
     *
     */

    public function forgotAction() {
        $request = $this->getRequest ();
        $form = $this->_getUserForgotForm ();

        if ($this->getRequest ()->isPost ()) {

            if ($form->isValid ( $request->getPost () )) {

                // collect the data from the form
                $f = new Zend_Filter_StripTags ( );
                $email = $f->filter ( $this->_request->getPost ( 'email' ) );

                $model = $this->_getModel ();
                $mailcheck = $model->checkEmail ( $email );

                if ($mailcheck == NULL) {
                    // failure: email does not exists on ddbb
                    $view = $this->initView ();
                    $view->error = $this->view->translate ( 'This email is not in our database. Please, try again.' );

                } else { // success: the email exists , so lets change the password and send to user by mail
                    //Zend_Debug::dump($mailcheck->toArray());
                    $mailcheck = $mailcheck->toArray ();


                    //regenerate the token
                    $mailcheck['token'] = md5 ( uniqid ( rand (), 1 ) );
                    // update the user with this token
                    $model->update ( $mailcheck );



                    //lets send the new token
                    $hostname = 'http://' . $this->getRequest ()->getHttpHost ();


                    $mail = new Zend_Mail ( );
                    $mail->setBodyHtml ( $this->view->translate ( 'Somebody , probably you, wants to restore your nolotiro access. Click on this url to restore your nolotiro account:' ).'<br />'
                            . $hostname . '/'.$this->view->lang.'/user/validate/t/'  .  $mailcheck['token'] .
                            '<br /><br />'.
                            $this->view->translate('Otherwise, ignore this message.').
                            '<br />--------------<br />' . utf8_decode ( $this->view->translate ( 'The nolotiro.org team.' ) ) );

                    //$mail->setFrom ( 'noreply@nolotiro.org', 'nolotiro.org' );
                    $mail->setFrom ( 'noreply@nolotiro', 'nolotiro.org' );

                    $mail->addTo ( $mailcheck ['email'] );
                    $mail->setSubject ( utf8_decode ( $this->view->translate ( 'restore your nolotiro.org  account' ) ) );
                    $mail->send ();

                    $this->_helper->_flashMessenger->addMessage ( $this->view->translate ( 'Check your inbox email to restore your nolotiro.org account' ) );

                    $this->_redirect ( '/'.$this->view->lang.'/ad/list/woeid/'.$this->location.'/ad_type/give' );

                }

            }
        }
        // assign the form to the view
        $this->view->form = $form;

    }

    /**
     * @abstract generate a text plain random password
     * remember it's no encrypted !
     * @return string (7) $pass
     */
    protected function _generatePassword() {
        $salt = "abcdefghjkmnpqrstuvwxyz123456789";
        mt_srand( ( double ) microtime () * 1000000 );
        $i = 0;
        while ( $i <= 6 ) {
            $num = mt_rand() %33;
            $pass .= substr ( $salt, $num, 1 );
            $i ++;
        }

        return $pass;
    }

    /**
     *
     * @return Form_UserForgotForm
     */
    protected function _getUserForgotForm() {
        require_once APPLICATION_PATH . '/forms/UserForgot.php';
        $form = new Form_UserForgot ( );
        return $form;
    }

    /**
     * Validate - check the token generated  sent by mail by registerAction, then redirect to
     * the logout  page (index home).
     * @param t
     *
     */
    public function validateAction() {

        //http://nolotiro.com/es/auth/validate/t/1232452345234
        $this->_helper->viewRenderer->setNoRender ( true );
        $token = $this->_request->getParam ( 't' ); //the token



        if (! is_null ( $token )) {

            //lets check this token against ddbb
            $model = $this->_getModel ();
            $validatetoken = $model->validateToken ( $token );

            $validatetoken = $validatetoken->toArray ();
            //Zend_Debug::dump ( $validatetoken );

            if ($validatetoken !== NULL) {

                //first kill previous session or data from client
                //kill the user logged in (if exists)
                Zend_Auth::getInstance ()->clearIdentity ();
                $this->session->logged_in = false;
                $this->session->username = false;

                
                $data ['active'] = '1';
                $data ['id'] = $validatetoken ['id'];

                //reset the token
                $data['token'] = NULL;
                //update token user in ddbb
                $model->update ( $data );


                //LETS OPEN THE GATE!
                //update the auth data stored
                $data = $model->fetchUser($validatetoken ['id']);
                $auth = Zend_Auth::getInstance ();
                $auth->getStorage()->write( (object)$data);


                $this->_helper->_flashMessenger->addMessage ( $this->view->translate ( 'Welcome' ) .' '. $data['username'] );
                

                $this->_redirect ( '/'.$this->view->lang.'/ad/list/woeid/'.$this->location.'/ad_type/give' );

            } else {
                
                throw new Zend_Controller_Action_Exception ( 'This token does not exist', 404 );

            }

        } else {
            $this->_helper->_flashMessenger->addMessage ( $this->view->translate ( 'Sorry, register url no valid or expired.' ) );
            $this->_redirect ( '/'.$this->view->lang.'/ad/list/woeid/'.$aNamespace->location.'/ad_type/give' );
        }

    }



    public function deleteAction() {
        $this->view->headTitle()->append( $this->view->translate ( 'Delete your profile' ) );

        $id = (int)$this->getRequest()->getParam('id');

        $auth = Zend_Auth::getInstance ();
        $model = $this->_getModel ();
        $user = $model->fetchUser( $id )->IdUser;


        if (($auth->getIdentity()->IdUser  == $user) ) { //if is the user profile owner lets delete it

            if ($this->getRequest()->isPost()) {
                $del = $this->getRequest()->getPost('del');
                if ($del == 'Yes') {
                    //delete user, and all his content
                    $model->deleteUser($id);
//                    $model->deleteUserComments($id);
//                    $model->deleteUserCommentsVotes($id);
//                    $model->deleteUserVotes($id);

                    //kill the session and go home
                    Zend_Auth::getInstance ()->clearIdentity ();
                    $this->session->logged_in = false;
                    $this->session->username = false;
                    $this->_helper->_flashMessenger->addMessage ( $this->view->translate ( 'Your account has been deleted.' ) );
                    $this->_redirect ( '/' );
                    return ;

                } else {
                    $this->_helper->_flashMessenger->addMessage ( $this->view->translate ( 'Nice to hear that' ) );
                    $this->_redirect ( '/' );
                    return ;
                }

            } else {
                $id = $this->_getParam('id', 0);

            }

        } else {

            $this->_helper->_flashMessenger->addMessage ( $this->view->translate ( 'You are not allowed to view this page' ) );
            $this->_redirect ( '/' );
            exit ();
        }
    }



    protected function _getModel() {
        if (null === $this->_model) {

            require_once APPLICATION_PATH . '/models/User.php';
            $this->_model = new Model_User ( );
        }
        return $this->_model;
    }

}
