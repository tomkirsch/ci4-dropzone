<?php namespace Tomkirsch\Dropzone;

use CodeIgniter\Files\File;
use CodeIgniter\Images\Image;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Images\Exceptions\ImageException;

class Dropzone{
	// config properties, or change via setters
	protected $chunkPath;
	protected $partFileExt;
	protected $chunkMaxAge;
	
	// these are read in POST
	protected $dzuuid;
	protected $chunkIndex;
	protected $chunkTotal;
	protected $fileSizeTotal;
	protected $clientName;
	
	// other properties
	protected $chunk;
	protected $assembledFile;
	protected $request;
	
	public function __construct($config=NULL){
		$pathConfig = config('paths');
		$this->chunkPath		= $config->chunkPath ?? $pathConfig->chunkPath ?? $pathConfig->writableDirectory ?? '';
		$this->partFileExt		= $config->partFileExt ?? '.part';
		$this->chunkMaxAge		= $config->chunkMaxAge ?? 60 * 60 * 4; // clean chunks older than 4 hours
		$this->request = service('request');
	}
	
	public function getChunkPath():string{
		return $this->chunkPath;
	}
	public function setChunkPath(string $path){
		return $this->chunkPath = $path;
	}
	public function getPartFileExt():string{
		return $this->partFileExt;
	}
	public function setPartFileExt(string $ext){
		return $this->partFileExt = $ext;
	}
	public function getChunkMaxAge():int{
		return $this->chunkMaxAge;
	}
	public function setChunkMaxAge(int $age){
		return $this->chunkMaxAge = $age;
	}
	
	// getters
	public function getId():?string{
		return $this->dzuuid;
	}
	public function getChunkIndex():?int{
		return $this->chunkIndex;
	}
	public function getChunkTotal():?int{
		return $this->chunkTotal;
	}
	public function getFileSizeTotal():?int{
		return $this->fileSizeTotal;
	}
	public function getChunk():?File{
		return $this->chunk;
	}
	public function getAssembledFile():?File{
		return $this->assembledFile;
	}
	public function isImage():bool{
		return is_a($this->assembledFile, 'CodeIgniter\Images\Image');
	}
	// NOTE: clientName will ONLY be sent with the chunk. If you need it to assemble the final file, you should send it from JS when calling assemble ajax
	public function getClientName():?File{
		return $this->clientName;
	}
	
	// read dropzone data and chunk from POST. Returns the uploaded file that has moved
	public function readChunk(string $fileInputName, array $postData=[]):UploadedFile{
		$this->readPost($postData);
		
		$this->chunkIndex = $this->request->getPost('dzchunkindex');
		if($this->chunkIndex === NULL) throw new \Exception('dzchunkindex was not sent in POST');
		
		// get the chunk and check it
		$this->chunk = $this->request->getFile($fileInputName); // CodeIgniter\HTTP\Files\UploadedFile
		if(!$this->chunk->isValid()) throw new \Exception($this->chunk->getErrorString().'('.$this->chunk->getError().')');
		
		// read original file name
		$this->clientName = $this->chunk->getClientName();
		
		// move it
		$fileName = $this->makeChunkFileName($this->chunkIndex);
		$this->chunk->move($this->chunkPath, $fileName, TRUE);
		
		return $this->chunk;
	}
	
	// assemble chunks - returns either an Image, or File
	public function assemble(string $destFile, bool $detectImage=TRUE, array $postData=[]):File{
		$this->readPost($postData);
		
		$chunks = [];
		$fileSize = 0;
		for($i=0; $i<$this->chunkTotal; $i++){
			$chunkName = $this->makeChunkFileName($i);
			$chunkFile = $this->chunkPath.'/'.$chunkName;
			if(!is_readable($chunkFile)) throw new \Exception('Cannot read chunk '.$chunkName);
			$chunks[] = $chunkFile;
			$fileSize += filesize($chunkFile);
		}
		if($fileSize !== $this->fileSizeTotal){
			// error?
		}
		$out = fopen($destFile, 'wb');
		foreach($chunks as $file){
			// binary read each chunk file we found from the earlier loop
			if(!$part = fopen($file, 'rb')){
				fclose($out);
				throw new \Exception('Failed to open file chunk '.$file);
			}
			// fwrite will append the data
			fwrite($out, fread($part, filesize($file)));
			fclose($part);
			unlink($file);
		}
		fclose($out);
		
		// image detection
		if($detectImage){
			$assembledFile = new Image($destFile, TRUE);
			try{
				$assembledFile->getProperties(FALSE);
			}catch(ImageException $err){
				// not an image
				$assembledFile = new File($destFile, TRUE);
			}
		}else{
			$assembledFile = new File($destFile, TRUE);
		}
		$this->assembledFile = $assembledFile;
		return $assembledFile;
	}
	
	public function delete(array $postData=[]):int{
		$numDeleted = 0;
		$this->readPost($postData);
		for($i=0; $i<$this->chunkTotal; $i++){
			$chunkName = $this->makeChunkFileName($i);
			$chunkFile = $this->chunkPath.'/'.$chunkName;
			if(file_exists($chunkFile)){
				unlink($chunkFile);
				$numDeleted++;
			}
		}
		return $numDeleted;
	}
	
	// cleanup old or canceled chunks
	public function cleanup(bool $forceClean=FALSE){
		if(!$forceClean && rand(0,10) > 5) return;
		if ($handle = opendir($this->chunkPath)) {
			while (FALSE !== ($file = readdir($handle))) {
				// relative dirs
				if ('.' === $file) continue;
				if ('..' === $file) continue;
				// directories/non-files
				if(!is_file($file)) continue;
				// non-chunks
				if(substr($file, strlen($this->partFileExt) * -1) !== $this->partFileExt) continue;
				// check modified time, to prevent current chunks from being removed
				$time = filemtime($this->chunkPath.'/'.$file);
				if($time && time() - $time > $this->chunkMaxAge){ // 4hr
					unlink($this->chunkPath.'/'.$file);
				}
			}
			closedir($handle);
		}
	}
	
	/*
		Post data should be standardized on every request.
		JS with Dropzone file:
		$.ajax({
			data: {
				dzuuid: 			file.upload.uuid,
				dztotalfilesize: 	file.size,
				dztotalchunkcount: 	file.upload.totalChunkCount,
			},
		});
	*/
	protected function readPost(array $data=[]){
		$this->dzuuid = $data['dzuuid'] ?? $this->request->getPost('dzuuid');
		if($this->dzuuid === NULL) throw new \Exception('dzuuid was not sent in POST');
		
		$this->chunkTotal = $data['dztotalchunkcount'] ?? $this->request->getPost('dztotalchunkcount');
		if($this->chunkTotal === NULL) throw new \Exception('dztotalchunkcount was not sent in POST');
		
		$this->fileSizeTotal = $data['dztotalfilesize'] ?? $this->request->getPost('dztotalfilesize');
		if($this->fileSizeTotal === NULL) throw new \Exception('dztotalfilesize was not sent in POST');
		
		$this->chunkIndex = intval($this->chunkIndex);
		$this->chunkTotal = intval($this->chunkTotal);
		$this->fileSizeTotal = intval($this->fileSizeTotal);
	}
	
	protected function makeChunkFileName(int $index):string{
		// we need the part- prefix, in case the uuid starts with a number or weird character
		$file = 'part-'.$this->dzuuid.'-'.$index.$this->partFileExt; // part-UUID-0.part
		$file = preg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $file); // Remove anything which isn't a word, whitespace, number, or any of the following caracters -_~,;[]().
		$file = preg_replace("([\.]{2,})", '', $file); // // Remove any runs of periods
		return $file;
	}
}
