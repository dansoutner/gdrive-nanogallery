<?php

if( ini_get('allow_url_fopen') ) {
	//die('allow_url_fopen is enabled. file_get_contents should work well');
	function get_url_content($request)
		{
			return file_get_contents($request);
		}
} else {
	//die('allow_url_fopen is disabled. file_get_contents would not work');
	function get_url_content($request)
		{
		$ch = curl_init($request);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		return curl_exec($ch);
		}
	}

// global consts
$apiKey = ''; // googleApi key
$defaultFolderId = ''; // id of photos root dir
$defaultPath = ''; // default path of this gdrive_nanogallery.php file
$defaultFolderImageUrl = ''; // url of png or jpg for default thumbnail
$showThumbnail = true;
$thumbnailHeight = 156;
$imageHeight = 700;
$imageHeightXS = 400;
$imageHeightSM = 600;
$imageHeightME = 750;
$imageHeightLA = 1000;
$imageHeightXL = 1400;


function retrieveFilesArray($folderId, $apiKey){
  // returns all files in GDrive folder
  $request = 'https://www.googleapis.com/drive/v3/files?pageSize=999&orderBy=name&q=%27'.$folderId.'%27+in+parents&fields=files(id%2CimageMediaMetadata%2Ftime%2CmimeType%2Cname)&key='.$apiKey;
  $response = get_url_content($request);
  echo $response;
  $response = json_decode($response, $assoc = true);
  $fileArray = $response['files'];
  return $fileArray;
}

function retrieveOneFileArray($folderId, $apiKey){ 
  // returns one file in GDrive folder
  $request = 'https://www.googleapis.com/drive/v3/files?pageSize=1&q=%27'.$folderId.'%27+in+parents&fields=files(id%2CimageMediaMetadata%2Ftime%2CmimeType%2Cname)&key='.$apiKey;
  $response = get_url_content($request);
  $response = json_decode($response, $assoc = true);
  $fileArray = $response['files'];
  return $fileArray;
}

function filterByMimeType($fileArray, $mimeType){ 
  // returns array of files only with mimeType
  $filteredFileArray = [];
  foreach($fileArray as $file){
    if (strpos($file['mimeType'], $mimeType) !== false){
      $filteredFileArray[] = $file;
    }
  }
  return $filteredFileArray;
}

function getfileIds($fileArray){ 
  // returns array of all IDs from input array
  $imageIdsArray = [];
  foreach($fileArray as $file){
    $imageIdsArray[] = $file['id'];
  }
  return $imageIdsArray;
}

function orderImagesByTime($imageArray){ 
  // returns array of images sorted by the date of picture is taken and name
  $sortingArray = array();
  foreach ($imageArray as $key => $image) {
    $sortingArray["time"][$key] = $image["imageMediaMetadata"]["time"];
    $sortingArray["name"][$key] = $image["name"];
  }
  array_multisort($sortingArray["time"], SORT_ASC, $sortingArray["name"], SORT_ASC, $imageArray);
  return $imageArray;
}

function retrieveImageIds($folderId, $apiKey){
  // returns all images in folder
  $fileArray = retrieveFilesArray($folderId, $apiKey);
  $filteredFileArray = orderImagesByTime(filterByMimeType($fileArray, "image/"));
  return getfileIds($filteredFileArray);
}

function retrieveOneImageId($folderId, $apiKey){
  // returns only one image for the folder (used for thumbnails)
  $fileArray = retrieveOneFileArray($folderId, $apiKey);
  $filteredFileArray = filterByMimeType($fileArray, "image/");
  return getfileIds($filteredFileArray);
}

function retrieveSubfolderArray($folderId, $apiKey){ 
  // returns array of all subfolders in folder
  $fileArray = retrieveFilesArray($folderId, $apiKey);
  $subfolderArray = filterByMimeType($fileArray, "folder");
  return $subfolderArray;
}

function retrieveFileName($fileId, $apiKey){
  // returns filename or dirname for input ID
  $request = "https://www.googleapis.com/drive/v3/files/$fileId?key=$apiKey";
  $response = get_url_content($request);
  $response = json_decode($response, $assoc = true);
  $fileName = $response['name'];
  return $fileName;
}


// process variables from GET
if($_GET['folderId'] != ""){
  $folderId = $_GET['folderId'];
}
else{
  $folderId = $defaultFolderId;
}

if($_GET['homeId'] != ""){
  $homeId = $_GET['homeId'];
}
else{
  $homeId = $folderId;
}

// retrive data
$folderName = retrieveFileName($folderId, $apiKey);
$subfolderArray = retrieveSubfolderArray($folderId, $apiKey);
$imageIds = retrieveImageIds($folderId, $apiKey);

// START OF HTML ----------------------------------------------------------------------------------------------
?> 

<html lang="cz">
  <head>
    <title><?= $folderName ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="http://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <script src="http://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <!-- Add jQuery library (MANDATORY) -->
    <script type="text/javascript" src="nanogallery/third.party/jquery-1.7.1.min.js"></script> 
    <!-- Add nanoGALLERY plugin files (MANDATORY) -->
    <link href="nanogallery/css/nanogallery.css" rel="stylesheet" type="text/css">
    <link href="nanogallery/css/themes/light/nanogallery_light.css" rel="stylesheet" type="text/css">
    <script type="text/javascript" src="nanogallery/jquery.nanogallery.js"></script>
    <link href="default.css" rel="stylesheet" type="text/css" media="screen">
  </head>


  <body>
    <div class="roundcontent" style="width: 96%; margin: 2%; margin-top: 10px; padding: 10px;">
      <?php
      if($homeId !== $folderId){
        echo "<h4 class='title'>".$folderName."</h4>";
        echo "<a href='".$defaultPath."?folderId=".$homeId."'><div class='back'><< zpátky</div></a><br>";
      }
      foreach($subfolderArray as $subfolder){
		echo "<div class='ngal_album'>";
        echo "<a href='".$defaultPath."?folderId=".$subfolder['id']."&homeId=".$homeId."'>";
		$thumbnailId = retrieveOneImageId($subfolder['id'], $apiKey)[0];
		$thumbnailWidth = 200;
		if (empty($thumbnailId)) {
			$thumbnailSrc = $defaultFolderImageUrl;
		} else {
			$thumbnailSrc = "https://drive.google.com/thumbnail?authuser=0&sz=w".$thumbnailWidth."&id=".$thumbnailId;
		}
		if (showThumbnail){
			echo "<div class='ngal_foto'><img src='".$thumbnailSrc."' width='".$thumbnailWidth."'></div>";
		}
        echo "<div class='ngal_content'>";
        //echo "<a href='".$defaultPath."?folderId=".$subfolder['id']."&homeId=".$homeId."'>".$subfolder['name']."</a>";
        echo "<div class='album-name'>".$subfolder["name"]."</div>";
        echo "</div>";
        echo "</a>";
        echo "</div>";
      }
      ?>
      <div id="nanoGalleryWrapperDrive"></div>
    </div>
  </body>
</html>


<!--JAVASCRIPT CODE-->
<script type="text/javascript">
  
  jQuery(document).ready(function () {
    jQuery("#nanoGalleryWrapperDrive").nanoGallery({
      items: [
            <?php //PHP code -----------------------------------------------------------------------------------
            foreach($imageIds as $id) {
              echo "{"."\r\n";
              echo "  src: 'https://drive.google.com/thumbnail?authuser=0&sz=h".$imageHeight."&id=".$id."',\r\n";
              echo "  srcXS: 'https://drive.google.com/thumbnail?authuser=0&sz=h".$imageHeightXS."&id=".$id."',\r\n";
              echo "  srcSM: 'https://drive.google.com/thumbnail?authuser=0&sz=h".$imageHeightSM."&id=".$id."',\r\n";
              echo "  srcME: 'https://drive.google.com/thumbnail?authuser=0&sz=h".$imageHeightME."&id=".$id."',\r\n";
              echo "  srcLA: 'https://drive.google.com/thumbnail?authuser=0&sz=h".$imageHeightLA."&id=".$id."',\r\n";
              echo "  srcXL: 'https://drive.google.com/thumbnail?authuser=0&sz=h".$imageHeightXL."&id=".$id."',\r\n";
              echo "  srct: 'https://drive.google.com/thumbnail?authuser=0&sz=h".$thumbnailHeight."&id=".$id."',\r\n";
              echo "  title: '',\r\n";
              echo "  description : ''\r\n";
              echo "},\r\n";
            }
            // END OF PHP -----------------------------------------------------------------------------------------
            ?>
        ],
      thumbnailWidth: 'auto',
      thumbnailHeight: 145,
      theme: 'light',
      colorScheme: 'none',
      thumbnailHoverEffect: [{ name: 'labelAppear75', duration: 300 }],
      thumbnailGutterWidth : 0,
      thumbnailGutterHeight : 0,
      slideshowDelay: 5000,
      i18n: { thumbnailImageDescription: 'Zvětšit', thumbnailAlbumDescription: 'Otevřít album' },
      thumbnailLabel: { display: true, position: 'overImageOnMiddle', align: 'center' }

    });
  });
</script>
