#!/usr/local/bin/php -q
<?php
$CBlack = "\033[30m";
$CRed = "\033[31m";
$CGreen = "\033[32m";
$CYellow = "\033[33m";
$CBlue = "\033[34m";
$CMagenta = "\033[35m";
$CCyan = "\033[36m";
$CWhite = "\033[37m";
//Color of background
$BBlack = "\033[40m";
$BRed = "\033[41m";
$BGreen = "\033[42m";
$BYellow = "\033[43m";
$BBlue = "\033[44m";
$BMagenta = "\033[45m";
$BCyan = "\033[46m";
$BWhite = "\033[47m";
//Reset of colors...
$CReset = "\033[m";
$config_file = "/cf/conf/config.xml";
$OpenVPN_Check_Time = 15;
//--------------------------------------------------------------------------
Startup();
//--------------------------------------------------------------------------
function Startup(){
	global $CYellow;
	global $CCyan;
	global $CRed;
	global $CGreen;
	Color_Output($CYellow,"###################################");
	Color_Output($CCyan,"This script will install OpenVPN");
	Color_Output($CYellow,"###################################");
	Color_Output($CRed,"This script may change Xigmanas configuration!!!\nPlease, close Xigmanas WebGUI before continue");
	echo($CWhite."Are you ready to start [y/n]?$CReset");
	$Answer = InputKeyboard();
	if(strtoupper($Answer) == "Y"){
		if(IsRunning("openvpn")){
			Color_Output($CGreen,"OpenVPN is running: stopping it...");
			exec("killall -c openvpn");
			Color_Output($CGreen,"...done!");
		}
		Pkg_Installer();
		Build_Directory();
		Conf_OpenVPN();
		Install_Cert();
		Create_UserPass();
		TestVPN();
		Set_Config();
	}
	else
		Color_Output($CCyan,"Script termited by user");
}
function Pkg_Installer(){
	global $CCyan;	
	global $CGreen;
	$pkg_list = array ("openvpn","curl","expect");
	$cnt = 1;
	$Res = exec("pkg info openvpn");
	if(strpos($Res,"openvpn")>-1){
		Color_Output($CCyan,"OpenVPN package already installed");
	}
	else
	{
		Color_Output($CCyan,"Installing packages: this may takes few minutes...");
		foreach($pkg_list as $Pkg){
			Color_Output($CCyan,"Installing ".$Pkg."(".$cnt."/".count($pkg_list).")...");
			exec("pkg install -y $Pkg");
			$cnt = $cnt + 1;
		}
		Color_Output($CCyan,"Packages installation complete");
		exec("rehash");		
	}
}
function Build_Directory(){
	global $CCyan;
	$Dir = "/usr/local/etc/openvpn";
	exec("cd /");
	Color_Output($CCyan,"Creating OpenVPN directory...");
	if(file_exists($Dir)==false)
		mkdir($Dir);
	if(file_exists($Dir."/openvpn") == false)
		exec("mv /usr/local/etc/rc.d/openvpn /usr/local/etc/openvpn/");
	Color_Output($CCyan,"...done");
}
function Conf_OpenVPN(){
	global $CCyan;
	global $CGreen;
	global $CWhite;
	Color_Output($CCyan,"Starting post install configuration...");
	$RC_Conf = parse_ini_file("/etc/rc.conf",true);
	if(is_array($RC_Conf)){
		if(array_key_exists("openvpn_enable",$RC_Conf) == false){
			exec('echo openvpn_enable=\"YES\" >> /etc/rc.conf');
		}
		else
			Color_Output($CGreen,"OpenVPN already enabled in /etc/rc.conf...");
		if(array_key_exists("openvpn_if",$RC_Conf) == false){
			$interface = InputKeyboard($CWhite."Which interface would you use?$Reset$CCyan T".$CWhite."un or$CCyan D".$CWhite."ev?[T/D]".$Reset);
			if(strtoupper($interface) == "T"){
				Color_Output($CCyan,"Adding tun interface...");
				exec('echo openvpn_if=\"tun\" >> /etc/rc.conf');
			}
			else{
				Color_Output($CCyan,"Adding dev interface...");
				exec('echo openvpn_if=\"dev\" >> /etc/rc.conf');
			}
		}
		else
			Color_Output($CGreen,"OpenVPN interface already defined in /etc/rc.conf...");	
	}
	else
		Color_Output($CGreen,"Invalid /etc/rc.conf");	
	Color_Output($CCyan,"...done");	
}
function Install_Cert(){
	global $CCyan;
	global $CGreen;
	global $CWhite;
	$conf_file ="/usr/local/etc/openvpn/openvpn.conf";
	$file = InputKeyboard($CWhite."Have you got a certificate/location file?[y/n]".$Reset);
	if(strtoupper($file)=="Y"){
		$location = InputKeyboard($CWhite,"Please, insert file location:".$Reset);
		while(file_exists($location) == false){
			Color_Output($CGreen,"Invalid file: please insert a valid location");
			$location = InputKeyboard($CWhite,"Please, insert file location or ENTER to exit:".$Reset);
			if($location == "\n")
				exit;
		}
		if(file_exists($conf_file))
			unlink($conf_file);
		if(file_exists($location))
			copy($location,$conf_file);
		else
			Color_Output($CGreen,"Invalid certificate/location file: OpenVPN may work wrong");
	}
	else{
paste_data:		
		$choose = InputKeyboard($CWhite."Would you past certificate/location file?[y/n]".$Reset);
		if(strtoupper($choose)=="Y"){
			Color_Output($CCyan,"Paste file data (add ENTER twice to complete):");
			$Data = InputMultiline();
			if(file_exists($conf_file))
				unlink($conf_file);			
			$datafile = fopen($conf_file,"w");
			fwrite($datafile,$Data);
			fclose($datafile);
		}
		else{
			$cse = InputKeyboard($CWhite."Without a certificate/location file, OpenVPN cannot work: would you continue?[y/n]".$Reset);
			if(strtoupper($cse) == "N")
				goto paste_data;
		}
	}
	Color_Output($CCyan,"...done");
}
function Create_UserPass(){
	global $CCyan;
	global $CGreen;
	$exec_file ="/usr/local/etc/openvpn/AutoLogin";
	$user = InputKeyboard($CCyan."Please insert username:".$Reset);
	//echo("\n");
	$pass = InputKeyboard($CCyan."Please insert password:".$Reset);
	//echo("\n");
	$login_data = "#!/usr/local/bin/expect -f\n";
	$login_data = $login_data."set force_conservative 0\n";
	$login_data = $login_data."spawn /usr/local/etc/openvpn/openvpn start /usr/local/etc/openvpn/openvpn.conf\n";
	$login_data = $login_data."match_max 100000\n";
	$login_data = $login_data."expect -exact \"Enter Auth Username:\"\n";
	$login_data = $login_data."send \"".$user."\"\n";
	$login_data = $login_data."send \"\\r\"\n";
	$login_data = $login_data."expect -exact \"Enter Auth Password:\"\n";
	$login_data = $login_data."send \"".$pass."\"\n";
	$login_data = $login_data."send \"\\r\"\n";
	$login_data = $login_data."expect eof\n";
	$datafile = fopen($exec_file,"w");
	fwrite($datafile,$login_data);
	fclose($datafile);
	exec("chmod a+x ".$exec_file);
}
function TestVPN(){
	global $CCyan;
	global $CGreen;
	global $OpenVPN_Check_Time;
	Color_Output($CCyan,"Testing VPN...");
	$original_IP = exec("curl -s icanhazip.com");
	Color_Output($CGreen,"Original IP:".$original_IP);
	Color_Output($CCyan,"Start OpenVPN...");
	exec("/usr/local/etc/openvpn/AutoLogin");
	$cnt = 0;
	while($cnt < $OpenVPN_Check_Time){
		$new_IP = exec("curl -s icanhazip.com");
		if(strcmp($original_IP, $new_IP) <> 0)
			break;
		sleep(1);	
		$cnt = $cnt + 1;				
	}
	if($cnt < $OpenVPN_Check_Time){
		Color_Output($CGreen,"New IP:".$new_IP);
	}
	else{
		if(IsRunning("openvpn") == false)
			Color_Output($CGreen,"OpenVPN doesn't start!!!");
		else
			Color_Output($CGreen,"OpenVPN don't change your IP: always ".$original_IP);
	}
}
function Set_Config(){
	global $config_file;
	$config = new DOMDocument("1.0");
	$config->preserveWhiteSpace = false;
	$config->formatOutput = true;				
	$config->load($config_file);
	$main = $config->documentElement;
	$Script_Loaded = false;
	Color_Output($CCyan,"Checking Xigmanas configuration...");
	foreach($main->childNodes as $item){
		$name = $item->nodeName;
		if(strrpos($name,"#text") === false){//node has a valid name...
			if($name == "rc"){	//rc node...
				foreach($item->childNodes as $subitem){
					$subname = $subitem->nodeName;
					if(strrpos($subname,"#text") === false){	//node has a valid name
						if($subname == "param"){
							foreach($subitem->childNodes as $childitem){
								$childname = $childitem->nodeName;
								if(strrpos($childname,"#text") === false){
									if($childname == "name"){	//ok: now we are in script leaf
										if($childitem->textContent == "OpenVPN script")
											$Script_Loaded = true;											
									}
								}
							}
						}
					}
				}
				if($Script_Loaded == false){
					Color_Output($CCyan,"OpenVPN doesn't start at boot: adding to configuration");
					$g = $config->createElement("param","");
					$g1 = $config->createElement("uuid","1f8de934-0318-4a32-b3af-a0ed82a1545e");
					$g2 = $config->createElement("name","OpenVPN script");
					$g3 = $config->createElement("value","/usr/local/etc/openvpn/AutoLogin");
					$g4 = $config->createElement("comment","Start OpenVPN at boot");
					$g5 = $config->createElement("typeid","1");
					$g6 = $config->createElement("enable","1");
					$g6->setAttribute("type","bool");			
					
					$g->appendchild($g1);
					$g->appendchild($g2);
					$g->appendchild($g3);
					$g->appendchild($g4);
					$g->appendchild($g5);
					$g->appendchild($g6);
					$item->appendchild($g);
					$config->save($config_file);
					//$config->save("/mnt/Open.xml");
					Color_Output($CCyan,"done");
				}				
				else
					Color_Output($CCyan,"OpenVPN already start at boot!!!");
			}
		}
	}
	/*if($Script_Loaded == false){
		
	}*/
}
//--------------------------------------------------------------------------
function InputMultiline(){
	$Out = "";
	$line = fgets(STDIN);
	while(($line !== FALSE) && strlen($line)>1){
		$Out = $Out.$line;
		$line = fgets(STDIN);
	}
	return $Out;
}
function Color_Output($color,string $Output){
	echo($color.$Output."\033[m\n");
}
function InputKeyboard($prompt=null){
	if($prompt <> null)
		echo($prompt);
	fscanf(STDIN,"%s",$pass);
	return $pass;
}
function IsRunning($ProcessName){
	$Res = exec("pgrep ".$ProcessName);
	if(strlen($Res) > 0)
		return true;
	return false;
}

?>
