<?php
/**
 * @package XRowCDN
 * @author  Serhey Dolgushev <dolgushev.serhey@gmail.com>
 * @date    09 Jan 2013
 **/

$clusterFileHandler = eZClusterFileHandler::instance( 'var' );
$clusterFiles       = $clusterFileHandler->getFileList( array( 'ezjscore' ) );
foreach( $clusterFiles as $clusterFile ) {
	$clusterFileHandler->fileFetch( $clusterFile );
}

// Get all local compressed JS and CSS files
//exec( 'find . -regex "./var.*cache/public.*\.\(css\|js\)" -print', $files );
exec( 'find . -regex "./.*\.\(css\|js\)" -print', $files );
exec( 'find . -regex "./extension.*\.\(png\|gif\|jpeg\|jpg\)" -print', $images );
$files = array_merge( $files, $images );

$time        = new DateTime();
$syncTimeVar = 'xrowcdn_compressed_jscss_time';

// Get last sync time
$lastSyncTime = eZSiteData::get( $syncTimeVar );
$lastSyncTime = new DateTime( ( $lastSyncTime === false ) ? '1970-01-01 00:00:00' : $lastSyncTime );
// Update last sync time
eZSiteData::set( $syncTimeVar, $time->format( DateTime::ISO8601 ) );

// Get new files
$newFiles = array();
foreach( $files as $file ) {
	$filemtime = filemtime( $file );
	if( new DateTime( '@' . $filemtime ) > $lastSyncTime ) {
		$newFiles[] = $file;
	}
}

// Get bucket
$ini = eZINI::instance( 'xrowcdn.ini' );
if( $ini->hasVariable( 'Rule-js', 'Bucket' ) === false ) {
	$cli->output( 'There is no "js" rule' );
	return false;
}

// Upload new files to CDN
$bucket = $ini->variable( 'Rule-js', 'Bucket' );
$cdn    = xrowCDN::getInstance();
foreach( $newFiles as $file ) {
	// Remove "./" from the filepath
	if( strpos( $file, './' ) === 0 ) {
		$file = substr( $file, 2 );
	}

	try {
		// GZip is not implemented yet
		$cdn->put( $file, $file, $bucket );
		$cli->output( '[UPLOAD] ' . $bucket . ':' . $file );
	} catch( Exception $e ) {
		$cli->output( '[FAILED] ' . $bucket . ':' . $file );
	}
}

// Remove expired files
$CDNFiles = $cdn->getAllDistributedFiles( $bucket );
foreach( $CDNFiles as $CDNFileInfo ) {
	if( strpos( $CDNFileInfo->name, '/cache/public/' ) !== false ) {
		if( file_exists( $CDNFileInfo->name ) === false ) {
			try {
				$CDNFileInfo->purge_from_cdn();
				$cli->output( '[REMOVED] ' . $bucket . ':' . $CDNFileInfo->name );
			} catch( Exception $e ) {
				$cli->output( '[FAILED] ' . $bucket . ':' . $CDNFileInfo->name . ' - ' . $e->getMessage() );
			}
		}
	}
}

$cli->output( 'Cronjob "Upload compressed JS and CSS files to CDN" is finished...' );
