<?php
namespace web\lib\admin\http;

use web\lib\admin\domain\SilverbulletUser;

class AddCertificateCommand extends AbstractCommand{

    const COMMAND = 'newcertificate';

    /**
     * 
     * {@inheritDoc}
     * @see \lib\http\AbstractCommand::execute()
     */
    public function execute(){
        $userId = $this->parseInt($_POST[self::COMMAND]);
        $user = SilverbulletUser::prepare($userId);
        $user->load();
        if($user->isExpired()){
            $this->storeErrorMessage(_("User '".$user->getUsername()."' has expired. In order to generate credentials please extend the expiry date!"));
        }else{
            $this->controller->createCertificate($user);
            if($user->isDeactivated()){
                $user->setDeactivated(false, $this->controller->getProfile());
                $user->save();
            }
        }
        $this->controller->redirectAfterSubmit();
    }

}
