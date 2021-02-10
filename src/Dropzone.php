<?php namespace Tomkirsch\Dropzone;

use CodeIgniter\Files\File;

class Dropzone{
	protected $chunkPath;
	protected $partFileExt;
	protected $request;
	
	protected $dzuuid;
	protected $chunkIndex;
	protected $chunkTotal;
	protected $fileSizeTotal;
	protected $chunk;
	
	protected $clientName;
	
	public function __construct($config=NULL){
		$pathConfig = config('paths');
		$this->chunkPath		= $config->chunkPath ?? $pathConfig->chunkPath ?? $pathConfig->writableDirectory ?? '';
		$this->partFileExt		= $config->partFileExt ?? '.part';
		$this->request = service('request');
	}
	
	// read dropzone data and chunk from POST. Returns the uploaded file that has moved
	public function readChunk(string $fileInputName):string{
		$this->readPost();
		
		$this->chunkIndex = $this->request->getPost('dzchunkindex');
		if($this->chunkIndex === NULL) throw new \Exception('dzchunkindex was not sent in POST');
		
		// get the chunk and check it
		$this->chunk = $this->request->getFile($fileInputName); // CodeIgniter\HTTP\Files\UploadedFile
		if(!$this->chunk->isValid()) throw new \Exception($this->chunk->getErrorString().'('.$this->chunk->getError().')');
		$this->clientName = $this->chunk->getClientName();
		
		// move it
		$fileName = $this->makeChunkFileName($this->chunkIndex);
		$this->chunk->move($this->chunkPath, $fileName, TRUE);
		
		return $this->chunkPath.'/'.$this->chunk->getName();
	}
	
	// assemble chunks. Make sure your JS passes the correct POST data
	public function assemble($destFile):string{
		$this->readPost();
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
		
		return $destFile;
	}
	
	public function delete($dzuuid){
		$this->readPost();
		for($i=0; $i<$this->chunkTotal; $i++){
			$chunkName = $this->makeChunkFileName($i);
			$chunkFile = $this->chunkPath.'/'.$chunkName;
			if(file_exists($chunkFile)) unlink($chunkFile);
		}
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
				if($time && time() - $time > 60 * 60 * 4){ // 4hr
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
				filename: file.name,
				dzuuid: file.upload.uuid,
				dztotalfilesize: file.size,
				dztotalchunkcount: file.upload.totalChunkCount,
			},
		});
	*/
	protected function readPost(){
		$this->dzuuid = $this->request->getPost('dzuuid');
		if($this->dzuuid === NULL) throw new \Exception('dzuuid was not sent in POST');
		
		$this->chunkTotal = $this->request->getPost('dztotalchunkcount');
		if($this->chunkTotal === NULL) throw new \Exception('dztotalchunkcount was not sent in POST');
		
		$this->fileSizeTotal = $this->request->getPost('dztotalfilesize');
		if($this->fileSizeTotal === NULL) throw new \Exception('dztotalfilesize was not sent in POST');
		
		$this->chunkIndex = intval($this->chunkIndex);
		$this->chunkTotal = intval($this->chunkTotal);
		$this->fileSizeTotal = intval($this->fileSizeTotal);
	}
	
	protected function makeChunkFileName(int $index):string{
		return $this->dzuuid.'.'.$index.$this->partFileExt; // UUID.0.part
	}
}
