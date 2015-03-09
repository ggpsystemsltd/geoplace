<?php
/**
 * GeoPlace Service Specification Demangler
 * 
 * @author Murray Crane <murray.crane@ggpsystems.co.uk>
 * @copyright (c) 2015, GGP Systems Limited
 * @license BSD 3-clause license (see below)
 * @version 1.1
 */

// Configuration variables
// *SET THESE APPROPRIATELY*
$usr_name = 'david.james@ggpsystems.co.uk';
$usr_pwd = 'Tr14l2015';
$authcode = '3645';

// *NO CHANGES NEEDED BELOW HERE*
// Set default dates for the query
$now_utc = new DateTime( 'now', new DateTimeZone( 'UTC' ));
$then_utc = new DateTime( 'now', new DateTimeZone( 'UTC' ));
$then_utc->sub( DateInterval::createFromDateString( '60 days' ));
$date_to = $now_utc->format( 'Y-m-d\TH:I:s.000\Z' );
$date_from = $then_utc->format( 'Y-m-d\TH:I:s.000\Z' );

// Prepare an HTTP request
$r = new HttpRequest( 'https://api.geoplace.co.uk/v1.0/cou', HttpRequest::METH_GET );

// Set the authentication headers of the request
$r->setHeaders( array( 'usr_name' => $usr_name,
	'usr_pwd' => $usr_pwd ));

// Validate any parameter dates
$args = array();
$request = filter_input_array( INPUT_GET, $args );
if( isset( $request[ 'date_from' ] ) &&
		is_string( $request[ 'date_from' ] )) {
	$dt = DateTime::createFromFormat( "Y-m-d", $request[ 'date_from' ] );
	$good = $dt !== false && !array_sum( $dt->getLastErrors() );
	if( $good ) {
		$date_from = $request[ 'date_from' ] . "T00:00:00.000Z";
	}
}
if( isset( $request[ 'date_to' ] ) &&
		is_string( $request[ 'date_to' ] )) {
	$dt = DateTime::createFromFormat( "Y-m-d", $request[ 'date_to' ] );
	$good = $dt !== false && !array_sum( $dt->getLastErrors() );
	if( $good ) {
		$date_to = $request[ 'date_to' ] . "T00:00:00.000Z";
	}
}

// Add the GET query data
$format = 'xml';
$r->addQueryData( array( 'format' => $format,
	'authcode' => $authcode,
	'date_from' => $date_from,
	'date_to' => $date_to ));

// Send the query, get the reponse
$filename = 'geoplace_' . $date_to . '.' . $format;
try {
	$r->send();
	$r_code = $r->getResponseCode();
	$r_body = $r->getResponseBody();
	if( $r_code == 200 ) {
		// Response good; write it to a temporary file
		file_put_contents( $filename, $r_body );
	} else {
		// Response bad; drop it on the browser for debugging
		echo $r_code . " - " . $r_body . "<br/>\n";
	}
} catch( HttpException $ex ) {
	// If we get here, things went *really* bad
	echo $ex;
}

// Was the temporary file written?
if( file_exists( $filename )) {
	// Open the file for stream handling
	$fp = fopen( $filename, 'rb' );
	// Send a couple of HTTP headers to the browser
	header( "Content-Type: text/xml" );
	header( "Content-Disposition: attachment; filename=" . $filename );
	header( "Content-Length: " . filesize( $filename ));
	// Stream the file to the browser
	fpassthru( $fp );
	// Delete the file
	unlink( $filename );
} else {
	// Catch all for problems
	echo "Oh snap! Something went wrong!";
}

/* BSD 3-clause license
 * 
 * Copyright (c) 2015, GGP Systems Ltd
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without 
 * modification are permitted provided that the following conditions
 * are met:
 * 
 * 1. Redistribution of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 
 * 2. Redistribution in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in
 *    the documentation and/or other materials provided with the
 *    distribution.
 * 
 * 3. Neither the name of the copyright holder nor the names of its
 *    contributors may be used to endorse or promote products derived
 *    from this software without specific written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDER AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISQUALIFIED. IN NO EVENT SHALL THE
 * COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, 
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; 
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN 
 * ANY WAY OUT OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
 * SUCH DAMAGE.
 */
/* End of file geoplace.php */
/* Location: ./geoplace.php */
