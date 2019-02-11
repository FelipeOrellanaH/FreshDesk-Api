<?php 

//****************************************** LEER EL ARCHIVO DE CONFIGURACION ***************************************
$filename = 'archivo_de_config.ini'; 
$data= parse_ini_file($filename,true);

//****************************************** LOGS ***************************************

$ar=fopen(dirname(__FILE__)."\logsFreshDesk\log_".date("Y-m-d").".txt", "a+");
fwrite($ar, "FECHA|TIPO|MENSAJE|ID" .PHP_EOL);

//****************************************** VARIABLES DEL ARCHIVO DE CONFIGURACION TRELLO ***************************************
$dia = time()-($data['date_import']['dias_atras']*24*60*60); //Te resta un dia (2*24*60*60) te resta dos y asi...
$dia_fin = date('Y-m-d', $dia);

//****************************************** MANEJO DE ERRORES ***************************************
error_reporting(E_ERROR | E_PARSE);
mysqli_report(MYSQLI_REPORT_STRICT);

date_default_timezone_set("America/Santiago");//DETERMINAR LA ZONA HORARIA

//****************************************** TIEMPO DE EJECUCIÓN ***************************************************
$tiempo_ejec = $data['date_import']['tiempo_maximo_de_ejecucion'];
ini_set('max_execution_time', $tiempo_ejec);


//****************************************** OBTENER TABLEROS ***************************************************
$curl = curl_init();
$urlBoards ='https://api.trello.com/1/members/me/boards?key='.$data['trello_config']['apikey'].'&token='.$data['trello_config']['token'].'';
curl_setopt($curl, CURLOPT_URL, $urlBoards);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$jsonBoard = curl_exec($curl);
curl_close($curl);	

$boards= json_decode($jsonBoard,true);

//***************************************************** RECORRER Y ALMACENAR TABLEROS ***************************************************

for ($i=0; $i < $data['tableros']['cantidad_tableros']; $i++)
{ 
	
	$shortLink =($data['tableros'][$i]);
	echo "Obteniendo los datos del tablero N°: ".$i.PHP_EOL." | ";

	
 //***************************************************** GET TARJETAS ***************************************************

	$urlTarjetas= 'https://api.trello.com/1/boards/'.$shortLink.'/cards?filter=all&since='.$dia_fin.'&key='.$data['trello_config']['apikey'].'&token='.$data['trello_config']['token'].''; 
	$conexionCard = curl_init();
	curl_setopt($conexionCard, CURLOPT_URL, $urlTarjetas);
	curl_setopt($conexionCard, CURLOPT_RETURNTRANSFER, true);
	$jsonCards = curl_exec($conexionCard);
	curl_close($conexionCard); 


	$cards = json_decode($jsonCards,true);
			
 //***************************************************** GET ACCIONES ***************************************************

	$urlAcciones ='https://trello.com/1/boards/'.$shortLink.'/actions?filter=addAttachmentToCard&key='.$data['trello_config']['apikey'].'&token='.$data['trello_config']['token'].''; 
	
	$conexionAcci= curl_init();
	curl_setopt($conexionAcci, CURLOPT_URL,$urlAcciones);
	curl_setopt($conexionAcci,CURLOPT_RETURNTRANSFER, true);
	$jsonAcci = curl_exec($conexionAcci);
	curl_close($conexionAcci);

	$actions= json_decode($jsonAcci,true);
		
	


	//***************************************************** RECCORRER LAS ACCIONES EN BUSCA DE LA IP DEL TICKET ***************************************************
	for ($p=0; $p <count($actions) ; $p++) 
	{ 	
		
		  //***************************************************** ACCION ADJUNTO ***************************************************
		$tipoAttachments = substr($actions[$p]['data']['attachment']['name'], 0,53); //NOMBRE DEL ADJUNTO
		
		if ($tipoAttachments =='https://neogisticaspa.freshdesk.com/helpdesk/tickets/')  //SI ES DE TIPO TICKET
		{	//***************************************************** ADJUNTO FRESHDESK ***************************************************
		


			//***************************************************** ALMACENAMOS LA ID CARD Y LA ID TICKET DE LA ACCION ***************************************************
			$idCard = $actions[$p]['data']['card']['id'];
			$idTicket = substr($actions[$p]['data']['attachment']['name'], 53,59);

			$archivada = "NADA"; //flag
			echo " La Tarjeta con la ID : |";
			echo $idCard;
			
			
			echo "|  , Tiene un estado : ";

			//***************************************************** VALIDAMOS QUE LA TARJETA CON EL ADJUNTO FRESHDESK  ESTÉ ARCHIVADA ***************************************************
			for ($o=0; $o < count($cards); $o++) 
			{ 
				if (($idCard == $cards[$o]['id']) && ($cards[$o]['closed'] == true )) 
				{	
					
					if ($data['lista']['cantidad_lista']=="*") 
					{
						$archivada = "ARCHIVED";
						echo (" |".$archivada."| ");
					}else
					{
						for ($b=0; $b <$data['lista']['cantidad_lista'] ; $b++) 
						{ 		
							if ($cards[$o]['idList']== $data['lista'][$b]) 
							{
								$archivada = "ARCHIVED";
								echo (" |".$archivada."| ");
							}

						}
					}
				}else if (($idCard == $cards[$o]['id']) && ($cards[$o]['closed'] == false )){

						if ($data['lista']['cantidad_lista']=="*") 
						{
						$archivada = "DESARCHIVADA";
						echo (" |".$archivada."| ");
					}else
					{
						for ($b=0; $b <$data['lista']['cantidad_lista'] ; $b++) 
						{ 		
							if ($cards[$o]['idList']== $data['lista'][$b]) 
							{
								$archivada = "DESARCHIVADA";
								echo (" |".$archivada."| ");
							}

						}
					}
				}




			//***************************************************** EN CASO DE QUE NO EXISTAN TARJETAS ARCHIVADAS CON TICKETS ***************************************************			
			} 

				if ($archivada == "NADA") {
				echo "|ERROR| -> | NO SE ENCUENTRA EN LA LISTA HECHO| ";
				$tipolog = "error_lista";		 
				createLog($tipolog,$idCard);

				}






			//*********************************************************************************************************************************************************************
			//***************************************************** SI LA TARJETA CON EL ADJUNTO FRESHDESK ESTA ARCHIVADA... ******************************************************
			//*********************************************************************************************************************************************************************
			//*********************************************************************************************************************************************************************
			if ($archivada == "ARCHIVED") 
			{
			
				
				$api_key = $data['freshdesk_config']['api_key'];
				$password = $data['freshdesk_config']['password'];



				//***************************************************** CONECTARSE A FRESHDESK ***************************************************

				$url = "https://".$data['freshdesk_config']['dominio'].$data['freshdesk_config']['URL'];
				$ch = curl_init($url);
			
				curl_setopt($ch, CURLOPT_USERPWD, "$api_key:$password");
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);	
				$server_output = curl_exec($ch);
				curl_close($ch);	

				
				$ticketsJson =json_decode($server_output,true); //entrega los atributos dividos en slash


				//***************************************************** RECORRER LOS TICKET DE FRESHDESK  ***************************************************
				for ($k=0; $k <count($ticketsJson); $k++) 
				{ 
						//***************************************************** SI LA ID DEL TICKET-TRELLO = ID DEL TICKET-FRESHDESK ***************************************************
					if ($ticketsJson[$k]['id'] == $idTicket) 
					{
					
						switch ($ticketsJson[$k]['status']) 
						{
							case 2: 
							 	$url = "https://".$data['freshdesk_config']['dominio'].$data['freshdesk_config']['URL'].$idTicket;
						 	 	$ch = curl_init($url);
						 	 	$header[] = "Content-type: application/json";
								$ticket_data = json_encode(array(
						 			"status" => 4,
						 			"description" => "La api funciona correctamente",
						 		));
						 		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
						 		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
						 		curl_setopt($ch, CURLOPT_HEADER, true);
						 		curl_setopt($ch, CURLOPT_USERPWD, "$api_key:$password");
						 		curl_setopt($ch, CURLOPT_POSTFIELDS, $ticket_data);
						 		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

						 		$server_output = curl_exec($ch);
						 		$info = curl_getinfo($ch);
						 		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
						 		$headers = substr($server_output, 0, $header_size);
						 		$response = substr($server_output, $header_size);

								 if($info['http_code'] == 200) 
					 				{
										echo "El Ticket con la ID : |".$idTicket."| se actualizó correctamente. \n";
										$tipolog = "estado_abierto";			 
										createLog($tipolog,$idTicket);
										
						 			} else 
									{
										$tipoerror = curl_error($ch);
										$tipolog = "error_estado_abierto";
										$numerror = $info['http_code'];
										createLog($tipolog,$idTicket,$numerror,$tipoerror);
						 				if($info['http_code'] == 404) 
											{
							 					echo "Error, Please check the end point \n";
											} else 
							 					{
							 						echo "Error, HTTP Status Code : " . $info['http_code'] . "\n";
							 					}
									}		
				     			curl_close($ch); 
				  			break;

					 		case 3:
								$url = "https://".$data['freshdesk_config']['dominio'].$data['freshdesk_config']['URL'].$idTicket;
								$ch = curl_init($url);
								$header[] = "Content-type: application/json";

			   		    		$ticket_data = json_encode(array(
				  				"status" => 4,
				  				"description" => "La api funciona correctamente",
				 		 		));

								curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
								curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
								curl_setopt($ch, CURLOPT_HEADER, true);
								curl_setopt($ch, CURLOPT_USERPWD, "$api_key:$password");
								curl_setopt($ch, CURLOPT_POSTFIELDS, $ticket_data);
								curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

								$server_output = curl_exec($ch);
								$info = curl_getinfo($ch);
								$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
								$headers = substr($server_output, 0, $header_size);
								$response = substr($server_output, $header_size);
								if($info['http_code'] == 200) 
									{
										echo "El Ticket con la ID : |".$idTicket."| se actualizó correctamente. \n";
									 	$tipolog = "estado_pendiente";
										createLog($tipolog,$idTicket);
										
									} else 

											$tipoerror = curl_error($ch);
											$numerror = $info['http_code'];
											$tipolog = "error_estado_pendiente";
											createLog($tipolog,$idTicket,$numerror,$tipoerror);
										{
											if($info['http_code'] == 404) 
								 			{
													echo "Error, Please check the end point \n";
											}else 
								  			{
							 	 				echo "Error, HTTP Status Code : " . $info['http_code'] . "\n";
						
								  			}
										}	 	
								curl_close($ch); 
							break;

							case 4:
								$tipolog = "estado_resuelto";
								createLog($tipolog,$idTicket);
							break;

							case 5:
								$tipolog = "estado_cerrado";
								createLog($tipolog,$idTicket);
							break;												
						}//switch
					}//if si la idTrello = idFresh
				}//recorrer los ticket
			}//end if card archivada






			//*********************************************************************************************************************************************************************
			//***************************************************** SI LA TARJETA CON EL ADJUNTO FRESHDESK ESTA DESARCHIVADA... ***************************************************
			//*********************************************************************************************************************************************************************
			//*********************************************************************************************************************************************************************
			if ($archivada == "DESARCHIVADA") 
			{
			
				$api_key = $data['freshdesk_config']['api_key'];
				$password = $data['freshdesk_config']['password'];



				//***************************************************** CONECTARSE A FRESHDESK ***************************************************

				$url = "https://".$data['freshdesk_config']['dominio'].$data['freshdesk_config']['URL'];
				$ch = curl_init($url);
			
				curl_setopt($ch, CURLOPT_USERPWD, "$api_key:$password");
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);	
				$server_output = curl_exec($ch);
				curl_close($ch);	

				
				$ticketsJson =json_decode($server_output,true); //entrega los atributos dividos en slash


				//***************************************************** RECORRER LOS TICKET DE FRESHDESK  ***************************************************
				for ($k=0; $k <count($ticketsJson); $k++) 
				{ 



						//***************************************************** SI LA ID DEL TICKET-TRELLO = ID DEL TICKET-FRESHDESK ***************************************************
					if ($ticketsJson[$k]['id'] == $idTicket) 
					{
						
						switch ($ticketsJson[$k]['status']) 
						{


							//*************************SI EL TICKET SE ENCUENTRA ABIERTO ***********************
							case 2: 
							 	$tipolog = "estado2_abierto";
								createLog($tipolog,$idTicket);
				  			break;


				  			//*************************SI EL TICKET SE ENCUENTRA PENDIENTE ***********************
					 		case 3: 
								$url = "https://".$data['freshdesk_config']['dominio'].$data['freshdesk_config']['URL'].$idTicket;
								$ch = curl_init($url);
								$header[] = "Content-type: application/json";

			   		    		$ticket_data = json_encode(array(
				  				"status" => 2,
				  				"description" => "La api funciona correctamente",
				 		 		));

								curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
								curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
								curl_setopt($ch, CURLOPT_HEADER, true);
								curl_setopt($ch, CURLOPT_USERPWD, "$api_key:$password");
								curl_setopt($ch, CURLOPT_POSTFIELDS, $ticket_data);
								curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

								$server_output = curl_exec($ch);
								$info = curl_getinfo($ch);
								$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
								$headers = substr($server_output, 0, $header_size);
								$response = substr($server_output, $header_size);

								if($info['http_code'] == 200) 
									{
										echo "El Ticket con la ID : |".$idTicket."| se actualizó correctamente. \n";
									 	$tipolog = "estado2_pendiente";
										createLog($tipolog,$idTicket);
										
									} else 
										{
											$tipoerror = curl_error($ch);
											$numerror = $info['http_code'];
											$tipolog = "error_estado2_pendiente";
											createLog($tipolog,$idTicket,$numerror,$tipoerror);
											if($info['http_code'] == 404) 
								 			{
													echo "Error, Please check the end point \n";
											}else 
								  			{
							 	 				echo "Error, HTTP Status Code : " . $info['http_code'] . "\n";
						
								  			}
										}	 	
								curl_close($ch); 
							break;



							//**************************SI EL TICKET SE ENCUENTRA RESUELTO ***********************
							case 4:

								$url = "https://".$data['freshdesk_config']['dominio'].$data['freshdesk_config']['URL'].$idTicket;
								$ch = curl_init($url);
								$header[] = "Content-type: application/json";

								//var_dump($idTicket);
			   		    		$ticket_data = json_encode(array(
				  				"status" => 2,
				  				"description" => "La api funciona correctamente",
				 		 		));


								curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
								curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
								curl_setopt($ch, CURLOPT_HEADER, true);
								curl_setopt($ch, CURLOPT_USERPWD, "$api_key:$password");
								curl_setopt($ch, CURLOPT_POSTFIELDS, $ticket_data);
								curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

								
								$server_output = curl_exec($ch);
								$info = curl_getinfo($ch);
								$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
								$headers = substr($server_output, 0, $header_size);
								$response = substr($server_output, $header_size);

								if($info['http_code'] == 200) 
									{
										echo "El Ticket con la ID : |".$idTicket."| se actualizó correctamente. \n";
									 	$tipolog = "estado2_resuelto";
										createLog($tipolog,$idTicket);
										
									} else 
										{
											/*
											$numerror = $info['http_code'];
											$tipoerror = curl_error($ch);
											if(curl_errno($ch))
											{
												var_dump($tipoerror, $numerror);
												echo "tipo error :". $tipoerror."n°:".$numerror;
											}
											$tipoerror = curl_error($ch); */
											$tipolog = "error_estado2_resuelto";
											
											createLog($tipolog,$idTicket,$numerror,$tipoerror);
											if($info['http_code'] == 404) 
								 			{
													echo "Error, Please check the end point \n";
											}else 
								  			{
							 	 				echo "Error, HTTP Status Code : " . $info['http_code'] . "\n";
												var_dump($info['http_code']);
								  			}
										}	 	
								curl_close($ch); 
							break;



							//*************************SI EL TICKET SE ENCUENTRA CERRADO ***********************
							case 5:
								$url = "https://".$data['freshdesk_config']['dominio'].$data['freshdesk_config']['URL'].$idTicket;
								$ch = curl_init($url);
								$header[] = "Content-type: application/json";

			   		    		$ticket_data = json_encode(array(
				  				"status" => 2,
				  				"description" => "La api funciona correctamente",
				 		 		));

								curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
								curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
								curl_setopt($ch, CURLOPT_HEADER, true);
								curl_setopt($ch, CURLOPT_USERPWD, "$api_key:$password");
								curl_setopt($ch, CURLOPT_POSTFIELDS, $ticket_data);
								curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

								$server_output = curl_exec($ch);
								$info = curl_getinfo($ch);
								$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
								$headers = substr($server_output, 0, $header_size);
								$response = substr($server_output, $header_size);

								if($info['http_code'] == 200) 
									{
										echo "El Ticket con la ID : |".$idTicket."| se actualizó correctamente. \n";
									 	$tipolog = "estado2_cerrado";
										createLog($tipolog,$idTicket);
									} else 
										{
											$numerror = $info['http_code'];
											$tipoerror = curl_error($ch);
											$tipolog = "error_estado2_cerrado";
											echo "tipo error :". $tipoerror;
											createLog($tipolog,$idTicket,$numerror,$tipoerror);
											if($info['http_code'] == 404) 
								 			{
													echo "Error, Please check the end point \n";
											}else 
								  			{
							 	 				echo "Error, HTTP Status Code : " . $info['http_code'] . "\n";
						
								  			}
										}	 	
								curl_close($ch); 
							break;												
						}//switch
					}//if si la idTrello = idFresh
				}//recorrer los ticket
			}//end if card archivada

		}//end if adjunto fresh
	}//end for actions	
}//end for boards	


		
	
function createLog($tipomsj,$id/*,$numerror,$tipoerror,*/)
{		
	
	$ar=fopen(dirname(__FILE__)."\logsFreshDesk\log_".date("Y-m-d").".txt", "a+");
	switch ($tipomsj) {

		//***************************************************** CASOS EXITOSOS ***************************************************
		case 'estado_abierto':
			fwrite($ar, "".date("Y-m-d H:i:s")."|S|El ticket con la id : |".$id."| cambio de estado [ABIERTO] a [RESUELTO] exitosamente " .PHP_EOL);
			break;
		case 'estado_pendiente':
			fwrite($ar, "".date("Y-m-d H:i:s")."|S|El ticket con la id : |".$id."| cambio de estado [PENDIENTE] a [RESUELTO] exitosamente ".PHP_EOL);
			break;
		case 'estado_resuelto':
			fwrite($ar, "".date("Y-m-d H:i:s")."|S|El ticket con la id : |".$id."| mantuvo su estado [RESUELTO] exitosamente ".PHP_EOL);
			break;
		case 'estado_cerrado':
			fwrite($ar, "".date("Y-m-d H:i:s")."|S|El ticket con la id : |".$id."| se encuentra [CERRADO] ".PHP_EOL);
			break;
		case 'estado2_abierto':
			fwrite($ar, "".date("Y-m-d H:i:s")."|S|El ticket con la id : |".$id."| mantuvo su estado [ABIERTO] exitosamente" .PHP_EOL);
			break;
		case 'estado2_pendiente':
			fwrite($ar, "".date("Y-m-d H:i:s")."|S|El ticket con la id : |".$id."| cambio de estado [PENDIENTE] a [ABIERTO] exitosamente ".PHP_EOL);
			break;
		case 'estado2_resuelto':
			fwrite($ar, "".date("Y-m-d H:i:s")."|S|El ticket con la id : |".$id."| cambio de estado  [RESUELTO] a [ABIERTO] exitosamente ".PHP_EOL);
			break;
		case 'estado2_cerrado':
			fwrite($ar, "".date("Y-m-d H:i:s")."|S|El ticket con la id : |".$id."| cambio de estado  [CERRADO] a [ABIERTO] exitosamente ".PHP_EOL);
			break;



		//***************************************************** CASOS ERRONEOS ***************************************************


		case 'error_estado_abierto':
			fwrite($ar, "".date("Y-m-d H:i:s")."|F|ERROR : |".$numerror."| El ticket con la id : |".$id."| NO pudo actualizarse, debido a : |".$tipoerror."| " .PHP_EOL);
			break;
		case 'error_estado_pendiente':
			fwrite($ar, "".date("Y-m-d H:i:s")."|F|ERROR : |".$numerror."| El ticket con la id : |".$id."| NO pudo actualizarse , debido a : |".$tipoerror."| ".PHP_EOL);
			break;
		case 'error_estado2_pendiente':
			fwrite($ar, "".date("Y-m-d H:i:s")."|F|ERROR : |".$numerror."| El ticket con la id : |".$id."| NO pudo actualizarse, debido a : |".$tipoerror." | ".PHP_EOL);
			break;
		case 'error_estado2_resuelto':
			fwrite($ar, "".date("Y-m-d H:i:s")."|F|ERROR : |".$numerror."| El ticket con la id : |".$id."| NO pudo actualizarse, debido a : |".$tipoerror."| ".PHP_EOL);
			break;
		case 'error_estado2_cerrado':
			fwrite($ar, "".date("Y-m-d H:i:s")."|F|ERROR : |".$numerror."| El ticket con la id : |".$id."| NO pudo actualizarse, debido a : |".$tipoerror."| ".PHP_EOL);
			break;


		//***************************************************** TARJETA EN LISSTA ERRONEA ***************************************************
		
		case 'error_lista':
		fwrite($ar, "".date("Y-m-d H:i:s")."|F|ERROR, la tarjeta con la id : |".$id."| NO se encuentra en la lista 'Hecho'.".PHP_EOL);
		break;

		}
}


?>