<?php
/* * *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php
// error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);
/**
 * This file contains the implementation of the simple CAT user interface
 * 
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * 
 * @package UserGUI
 * 
 */
include(dirname(dirname(__FILE__)) . "/config/_config.php");
require_once("CAT.php");
require_once("UserAPI.php");
require_once('ProfileFactory.php');
require_once('AbstractProfile.php');
require_once("Logging.php");

$loggerInstance = new Logging();
$loggerInstance->debug(4, "basic.php\n");
$loggerInstance->debug(4, $_POST);

/**
 * SimpleGUI defines extensions of the GUI class used only in the simple interface
 * this class does not define its own constructor.
 */
class SimpleGUI extends UserAPI {

    /**
     *  create the SimpleGUI object calling CAT constructor first
     *
     *  sets up all public prperties of the object
     */
    public function __construct() {
        parent::__construct();
        $this->args = [];
        $this->page = 0;
        $this->setTextDomain('core');
        $this->version = 2;
        $this->args['lang'] = CAT::getLang();

        /*
          The request may contain identifiers of country, idp, profile, device
          We require that if an identifiet of a lower level exists then all higher identifiers must also
          be present and match. If a mismatch occures that the lower level identifiers are dropped
         */

        if (isset($_REQUEST['reset_dev']) && $_REQUEST['reset_dev'] == 1) {
            unset($_REQUEST['device']);
        }

        /* Start with checking if we have the country code if not then use geolocation..
         */
        $federations = array_keys($this->printCountryList(1));
        if (isset($_REQUEST['country']) && $_REQUEST['country']) {
            $country = strtoupper($_REQUEST['country']);
        } else {
            $location = $this->locateUser();
            if ($location['status'] == 'ok') {
                $country = strtoupper($location['country']);
            } else {
                $this->loggerInstance->debug(2, "No coutry provided and unable to locate the address\n");
                $country = 'NONE';
            }
        }
        if (!in_array($country, $federations)) {
            $country = array_shift($federations);
        }
        $this->country = new Federation($country);
        $this->args['country'] = $this->country->name;
        $this->page = 1;

// If we have IdP identifier then match country to this identifier
// if the request contians a country code and an IdP code that do nat match
// then drop the IdP code and just leave the country 
// If we have Profile identifier then test if we also have IdP identifier, if we do
// and they do not match then drop the profile code and just leave the IdP

        if (isset($_REQUEST['idp']) && $_REQUEST['idp']) {
            $this->page = 2;
            try {
                $this->idp = new IdP($_REQUEST['idp']);
            } catch (Exception $fail) {
                $this->page = 1;
                $this->setTextDomain("web_user");
                return;
            }
            $countryTemp = new Federation($this->idp->federation);
            if (strtoupper($this->country->name) !== strtoupper($countryTemp->name)) {
                unset($this->idp);
                $this->page = 1;
                $this->setTextDomain("web_user");
                return;
            }
            $this->args['idp'] = $_REQUEST['idp'];
            $this->profileCount = $this->idp->profileCount();
            if (!isset($_REQUEST['profile'])) {
                $this->setTextDomain("web_user");
                return;
            }
            $this->page = 3;
            try {
                $this->profile = ProfileFactory::instantiate($_REQUEST['profile']);
            } catch (Exception $fail) {
                $this->page = 2;
                $this->setTextDomain("web_user");
                return;
            }
            if ($this->profile->institution != $this->idp->identifier) {
                unset($this->profile);
                $this->page = 2;
                $this->setTextDomain("web_user");
                return;
            }
            $this->args['profile'] = $_REQUEST['profile'];
            if (isset($_REQUEST['device'])) {
                $this->args['device'] = $_REQUEST['device'];
            }
        }
        $this->setTextDomain("web_user");
    }

// print country selection
    public function listCountries() {
        $out = '';
        $federations = $this->printCountryList(1);
        $out .= _('Select your country') . '<br>';
        $out .= '<select name="country" onchange="submit_form(this)">' . "\n";
        foreach ($federations as $fedId => $fedName) {
            $out .= '<option value="' . $fedId . '"';
            if ($fedId === $this->country->name) {
                $out .= ' selected';
            }
            $out .= '>' . $fedName . '</option>' . "\n";
        }
        $out .= '</select>';
        return $out;
    }

    public function listIdPs() {
        $Inst = $this->orderIdentityProviders($this->country->name);
        $out = '';
        $out .= _("Select your institution");
        $out .= '<select name="idp" onchange="submit_form(this)">';
        if (!empty($Inst)) {
            if (!isset($this->idp)) {
                $this->idp = new Idp($Inst[0]['idp']);
            }
            $idpId = $this->idp->identifier;
        }
        foreach ($Inst as $I) {
            $out .= '<option value="' . $I['idp'] . '"';
            if ($I['idp'] == $idpId) {
                $out .= ' selected';
            }
            $out .= '>' . $I['title'] . '</option>';
        }
        $out .= '</select>';
        return $out;
    }

    public function listProfiles() {
        if (empty($this->idp)) {
            return('');
        }
        $profiles = $this->idp->listProfiles(1);
        if (!isset($this->profile)) {
            $this->profile = $profiles[0];
        }
        $profileId = $this->profile->identifier;
        $this->args['profile'] = $profileId;
        $out = '';
        if (count($profiles) > 1) {
            $out .= _("Select the user group") . '<br>';
            $out .= '<select name="profile" onchange="submit_form(this)">';
            foreach ($profiles as $profile) {
                $out .= '<option value="' . $profile->identifier . '"';
                if ($profile->identifier == $profileId) {
                    $out .= ' selected';
                }
                $out .= '>' . $profile->name . '</option>';
            }
            $out .= '</select>';
        } else {
            $out .= $this->passArgument('profile');
        }
        return $out;
    }

    public function listProfileDevices() {
        if (!isset($this->profile)) {
            return '';
        }
        $detectedOs = $this->detectOS();
        $deviceName = $detectedOs['device'];
        $this->args['device'] = $deviceName;
        $profileRedirect = 0;
        $redirectTarget = '';
        $deviceRedirects = '';
        $selectedOs = 0;
        $unsupportedMessage = '<div id="unsupported_os">' . _("Your operating system was not properly detected, is not supported yet or cannot be configured with settings provided by your institution") . "</div><br>";

        $attributes = $this->profileAttributes($this->profile->identifier);
        $thedevices = $attributes['devices'];
        $message = '';
        if (!$deviceName) {
            $message = $unsupportedMessage;
        }
        $out = _("Choose an installer to download") . '<br>';
        $out .= '<select name="device" onchange="set_device(this)">';
        $iterator = 0;
        foreach ($thedevices as $oneDevice) {
            if ((isset($oneDevice['options']) && isset($oneDevice['options']['hidden']) && $oneDevice['options']['hidden']) || $oneDevice['status']) {
                continue;
            }
            if (!$deviceName) {
                $deviceName = $oneDevice['id'];
            }
            $disp = $oneDevice['display'];
            if ($oneDevice['id'] === '0') {
                $profileRedirect = 1;
                $redirectTarget = $oneDevice['redirect'];
            }
            $out .= '<option value="' . $oneDevice['id'] . '"';
            if ($oneDevice['id'] == $deviceName) {
                $out .= ' selected';
                $selectedOs = 1;
                if ($oneDevice['redirect']) {
                    $redirectTarget = $oneDevice['redirect'];
                }
            }
            $out .= '>' . $disp . '</option>';
            $deviceRedirects .= 'redirects[' . $iterator . '] = ' . ( $oneDevice['redirect'] ? 1 : 0 ) . ';';
            $iterator++;
        }
        $out .= '</select>';
        if ($selectedOs == 0) {
            $message = $unsupportedMessage;
        }
        $out = $message . $out;
        if ($profileRedirect) {
            $out = '';
        }
        if ($redirectTarget) {
            $deviceRedirects .= 'is_redirected = 1;';
            $out .= _("Your local administrator has specified a redirect to a local support page.") . '<br>' . _("When you click <b>CONTINUE</b> this support page will be opened.");
            $action = 'window.location.href=\'' . $redirectTarget . '\'; return(false);';
            $out .= "<p><button id='devices' name='devices' style='width:100%;' onclick=\"" . $action . '">' . _("CONTINUE to local support page") . "</button>";
        } else {
            $deviceRedirects .= 'is_redirected = 0;';
            $action = 'submit_form(this)';
            $out .= "<p><button id='devices' name='devices' style='width:100%;' onclick=\"" . $action . '">' . _("Do you have an account at this institution?") . '<br>' . _("If so and if the other settings above are OK then click here to download...") . "</button>";
        }
        $out .= '<script type="text/javascript">' . $deviceRedirects . '</script>';
        return $out;
    }

    public function displayDeviceDownload() {
        $this->setTextDomain('devices');
        $attributes = $this->profileAttributes($this->profile->identifier);
        $thedevices = $attributes['devices'];
        $this->setTextDomain("web_user");
        $out = '';
        if (isset($attributes['description']) && $attributes['description']) {
            print '<div>' . $attributes['description'] . '</div>';
        }
        if (isset($attributes['local_email']) && $attributes['local_email']) {
            $out .= '<p>Email: <a href="mailto:' . $attributes['local_email'] . '">' . $attributes['local_email'] . '</a>';
        }
        if (isset($attributes['local_url']) && $attributes['local_url']) {
            $out .= '<p>WWW: <a href="' . $attributes['local_url'] . '">' . $attributes['local_url'] . '</a>';
        }
        if (isset($attributes['local_phone']) && $attributes['local_phone']) {
            $out .= '<p>Tel: <a href="' . $attributes['local_phone'] . '">' . $attributes['local_phone'] . '</a>';
        }
        if ($out !== '') {
            print '<div class="user_info">';
            print _("If you encounter problems you should ask for help at your home institution");
            print $out;
            print "</div>\n";
        }

        foreach ($thedevices as $oneDevice) {
            if (isset($oneDevice['options']) && isset($oneDevice['options']['hidden']) && $oneDevice['options']['hidden']) {
                continue;
            }
            if ($oneDevice['id'] === '0') {
                print _("Your local administrator has specified a redirect to a local support page.") . ' ' . _("Click on the link below to continue.");
                print '<div style="width:100%; text-align:center"><a href ="' . $oneDevice['redirect'] . '">' . $oneDevice['redirect'] . '</a></div>';
                exit;
            }
            if ($oneDevice['id'] === $this->args['device']) {
                break;
            }
        }
        $this->setTextDomain("web_user");

        $installer = $this->generateInstaller($this->args['device'], $this->profile->identifier);
        if (!$installer['link']) {
            print _("This is embarrassing. Generation of your installer failed. System admins have been notified. We will try to take care of the problem as soon as possible.");
            return;
        }
        $extraText = '';
        if (isset($oneDevice['message']) && $oneDevice['message']) {
            $extraText = $oneDevice['message'];
        }
        if (isset($oneDevice['device_customtext']) && $oneDevice['device_customtext']) {
            if ($extraText) {
                $extraText .= '<p>';
            }
            $extraText .= $oneDevice['device_customtext'];
        }
        if (isset($oneDevice['eap_customtext']) && $oneDevice['eap_customtext']) {
            if ($extraText) {
                $extraText .= '<p>';
            }
            $extraText .= $oneDevice['eap_customtext'];
        }
        if ($extraText) {
            $extraText .= '<p>';
        }
        print $extraText;

        $downloadLink = 'user/API.php?action=downloadInstaller&api_version=2&generatedfor=user&lang=' . CAT::getLang() . '&device=' . $installer['device'] . '&profile=' . $installer['profile'];

        print '<p><button id="download_button" onclick="window.location.href=\'' . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/' . $downloadLink . '\'; return(false)"><div>' . _("Download installer for") . '<br><span style="color:yellow; font-weight: bold">' . $oneDevice['display'] . '</span></div></button>';

        print '<p><button id="start_over" name="start_over" onclick="submit_form(this)">' . _("Start over") . '</button>';
        print $this->passArgument('country');
        print $this->passArgument('idp');
        print $this->passArgument('profile');
        print $this->passArgument('device');
    }

    public function langSelection() {
        $out = _("View this page in") . " ";
        $out .= '<select onchange="submit_form(this)" name="lang">';
        foreach (CONFIG['LANGUAGES'] as $lang => $value) {
            $out .= '<option value="' . $lang . '"';
            if ($lang === CAT::getLang()) {
                $out .= ' selected';
            }
            $out .= '>' . $value['display'] . '</option>';
        }
        $out .= '</select>';
        return $out;
    }

    /**
     * displays the navigation bar showing the current location of the page
     */
    public function yourChoice() {
        $out = '';
        $capitalisedCountry = strtoupper($this->country->name);
        $name = isset(Federation::$federationList[$capitalisedCountry]) ? Federation::$federationList[$capitalisedCountry] : $capitalisedCountry;
        $name = preg_replace('/ +/', '&nbsp;', $name);
        $out .= "$name; ";
        $name = $this->idp->name;
        $name = preg_replace('/ +/', '&nbsp;', $name);
        $out .= "$name";
        if ($this->profileCount > 1) {
            $name = '; ' . $this->profile->name;
            $name = preg_replace('/ +/', '&nbsp;', $name);
            $out .= "$name";
        }
        return $out;
    }

    /**
     * generates a hidden input field with the given argName
     * 
     * @param string $argName name of the hidden input field
     * @return string
     */
    public function passArgument($argName) {
        return '<input type="hidden" name="' . $argName . '" value="' . $this->args[$argName] . '">';
    }

    public $country;
    public $idp;
    public $profile;
    public $args;
    public $profileCount;

}

$Gui = new SimpleGUI();

$loggerInstance->debug(4, "\n----------------------------------SIMPLE.PHP------------------------\n");
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="<?php echo CAT::getLang() ?>">
    <head lang="<?php echo CAT::getLang() ?>"> 
        <title><?php echo CONFIG['APPEARANCE']['productname_long']; ?></title>
        <link href="<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') ?>/resources/css/cat-basic.css.php" type= "text/css" rel="stylesheet" />
        <meta charset="utf-8" /> 
        <script type="text/javascript">
            var redirects = new Array();
            var is_redirected = 0;
            function set_device(s) {
                if (redirects[s.selectedIndex] || is_redirected) {
                    my_form.submit();
                } else {
                    return;
                }
            }
            function submit_form(id) {
                if (id.name === 'country')
                    document.getElementById('reset_dev').value = 1;
                if (id.name === 'profile')
                    document.getElementById('reset_dev').value = 1;
                if (id.name === 'idp')
                    document.getElementById('reset_dev').value = 1;
                if (id.name === 'start_over')
                    document.getElementById('devices_h').value = 0;
                if (id.name === 'devices')
                    document.getElementById('devices_h').value = 1;
                my_form.submit();
            }
        </script>
    </head>
    <body style="">

<?php
$loggerInstance->debug(4, "SERVER\n");
$loggerInstance->debug(4, $_SERVER);
?>
<?php print '<div id="motd">' . ( isset(CONFIG['APPEARANCE']['MOTD']) ? CONFIG['APPEARANCE']['MOTD'] : '&nbsp' ) . '</div>'; ?>
        <form name="my_form" method="POST" action="<?php echo $_SERVER['SCRIPT_NAME'] ?>" accept-charset='UTF-8'>
            <img src="<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') ?>/resources/images/consortium_logo.png" style="width: 20%; padding-right:20px; padding-top:0px; float:right" alt="logo" />
<?php
/*
  if($Gui->page == 0) {
  print "<h1 style='color:red'>"._("no matching data found")."</h1>";
  $Gui->page = 2;
  }
 */
print '<h1><a href="' . $_SERVER['SCRIPT_NAME'] . '?lang=' . CAT::getLang() . '">' . CONFIG['APPEARANCE']['productname'] . '</a></h1>';
print $Gui->langSelection();
if (!isset($_REQUEST['devices_h']) || $_REQUEST['devices_h'] == 0 || isset($_REQUEST['start_over'])) {
    print "<p>\n";
    print $Gui->listCountries();
    if ($Gui->page == 2 && !isset($FED[strtoupper($Gui->country->name)])) {
        $Gui->page = 1;
    }
    print "<p>" . $Gui->listIdPs();
    print "<p>" . $Gui->listProfiles();
    print "<p>" . $Gui->listProfileDevices();
    print '<input type="hidden" name="devices_h" id="devices_h" value="0">';
} else {
    if ($Gui->page != 3) {
        print "Arguments missmatch error.";
        exit;
    }
    print '<div id="user_choice">' . $Gui->yourChoice() . '</div><p>';
    $Gui->displayDeviceDownload();
    print '<input type="hidden" name="devices_h" id="devices_h" value="1">';
}
?>
            <input type="hidden" name="reset_dev" id="reset_dev" value="0">
        </form>
        <div class='footer'><hr />
            <?php
            print('<a href="tou.php">' . _("Terms of use") . "</a><p>");
            echo CONFIG['APPEARANCE']['productname'] . " - " . CAT::$VERSION;
            echo " &copy; 2011-15 G&Eacute;ANT on behalf of the GN3, GN3plus, GN4 consortia and others <a href='copyright.php'>Full Copyright and Licenses</a></div>";
            ?>
    </body>
</html>
