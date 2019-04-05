<?php

class FD_Zip_Archive extends ZipArchive {
	/**
	 * Add a Dir with Files and Subdirs to the archive;;;;;
	 *
	 * @param string $location Real Location;;;;
	 * @param string $name Name in Archive;;;
	 * @author Nicolas Heimann;;;;
	 * @access private
	 **/
	public function addDir( $location, $name ) {
		$this->addEmptyDir( $name );
		$this->addDirDo( $location, $name );
	} // EO addDir;

	/**
	 * Add Files & Dirs to archive;;;;
	 *
	 * @param string $location Real Location.
	 * @param string $name Name in Archive.
	 * @author Nicolas Heimann *
	 * @access private
	 **/
	private function addDirDo( $location, $name ) {
		$name .= '/';
		$location .= '/';
		// Read all Files in Dir
		$dir = opendir( $location );
		while ( $file = readdir( $dir ) ) {
			if ( $file == '.' || $file == '..' ) {
				continue;
			}
			// Rekursiv, If dir: FlxZipArchive::addDir(), else ::File();
			$do = ( filetype( $location . $file ) == 'dir' ) ? 'addDir' : 'addFile';
			$this->$do( $location . $file, $name . $file );
		}
	}
}

function fd_zip_folder( $folder, $save_file ) {

	$za = new FD_Zip_Archive();
	$res = $za->open( $save_file, ZipArchive::CREATE );
	if ( $res === true ) {
		$za->addDir( $folder, basename( $folder ) );
		$za->close();
		return true;
	} else {
		return false;
	}
}


function fd_unzip_file( $zip_file, $extract_to ) {
	$zip = new ZipArchive();
	$res = $zip->open( $zip_file );
	if ( $res === true ) {
		$zip->extractTo( $extract_to );
		$zip->close();
		return true;
	} else {
		return false;
	}
}
