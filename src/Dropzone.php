<?php namespace Tomkirsch\Dropzone;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\Files\File;

class Dropzone{
	protected $request;
	protected $chunkPath;
	protected $partFileExt;
	protected $dzuuid;
	protected $chunkIndex;
	protected $chunkTotal;
	protected $fileSizeTotal;
	protected $chunkSolo;
	protected $clientName;
	protected $finalPath;
	protected $finalName;
	protected $finalFile;
	protected $currentChunk;
	protected $isComplete = FALSE;
	
	public function __construct($config){
		$pathConfig = config('paths');
		$this->chunkPath		= $config->chunkPath ?? $pathConfig->chunkPath ?? $pathConfig->writableDirectory ?? '';
		$this->partFileExt		= $config->partFileExt ?? '.part';
		if(!empty($this->chunkPath) && substr($this->chunkPath, -1) !== '/') $this->chunkPath .= '/';
	}
	
	public function isComplete():bool{
		return $this->isComplete;
	}
	
	public function getFinalFile():File{
		return $this->finalFile;
	}
	
	public function getClientName():string{
		return $this->clientName;
	}
	
	public function getDzuuid():string{
		return $this->dzuuid;
	}
	
	public function readChunk(IncomingRequest $request, string $fileInputName, string $finalPath, string $finalName='', bool $overwrite=TRUE, array $post=[]):bool{
		if(empty($post)) $post = $request->getPost();
		if(!isset($post['dzuuid'])) throw new \Exception('No Dropzone UUID supplied');
		if(!isset($post['dzchunkindex'])) throw new \Exception('No Dropzone Chunk Index supplied');
		if(!isset($post['dztotalchunkcount'])) throw new \Exception('No Dropzone Chunk Count supplied');
		if(!isset($post['dztotalfilesize'])) throw new \Exception('No Dropzone Total File Size supplied');
		
		$this->isComplete = FALSE;
		$this->request = $request;
		$this->finalPath = $finalPath;
		if(!empty($this->finalPath) && substr($this->finalPath, -1) !== '/') $this->finalPath .= '/';
		
		// read POST
		$this->dzuuid = $post['dzuuid'];
		$this->chunkIndex = intval($post['dzchunkindex']);
		$this->chunkTotal = intval($post['dztotalchunkcount']);
		$this->fileSizeTotal = intval($post['dztotalfilesize']);
		$this->chunkSolo = ($this->chunkIndex === 0 && $this->chunkTotal === 1);
		
		// read file from request
		$this->currentChunk = $this->request->getFile($fileInputName); // CodeIgniter\HTTP\Files\UploadedFile
		if(!$this->currentChunk->isValid()) throw new \Exception($this->currentChunk->getErrorString().'('.$this->currentChunk->getError().')');
		$this->clientName = $this->currentChunk->getClientName();
		
		// move file to chunk directory, or the final destination if it's only 1 chunk
		$filePath = $this->chunkSolo ? $this->finalPath : $this->chunkPath;
		// if we're assembling chunks, rename the file to ensure there are no conflicts
		$this->finalName = empty($finalName) ? $this->clientName : $finalName;
		if(empty($this->finalName)) throw new \Exception('Dropzone::readChunk() - Cannot determine final file name');
		// rename the final file if need be
		if(!$overwrite){
			$this->checkExistingFile();
		}
		
		$fileName = $this->chunkSolo ? $this->finalName : $this->makeChunkFileName($this->chunkIndex);
		
		// perform the move/rename
		$this->currentChunk->move($filePath, $fileName, TRUE);
		
		if($this->chunkSolo){
			// we should be all done here
			$this->isComplete = TRUE;
			// currentChunk was moved
			$this->finalFile = new File($filePath.$fileName, TRUE);
		}else{
			// assemble chunks if they're all in place and set finalFile
			$this->isComplete = $this->checkChunks();
		}
		return $this->isComplete;
	}
	
	// cleanup old or canceled chunks
	public function cleanup(bool $forceClean=FALSE){
		if(!$forceClean && rand(0,10) > 5) return;
		
		if ($handle = @opendir($this->chunkPath)) {
			while (FALSE !== ($file = @readdir($handle))) {
				// relative dirs
				if ('.' === $file) continue;
				if ('..' === $file) continue;
				// directories/non-files
				if(!is_file($file)) continue;
				// non-chunks
				if(substr($file, strlen($this->partFileExt) * -1) !== $this->partFileExt) continue;
				// check modified time, to prevent current chunks from being removed
				$time = @filemtime($this->chunkPath.$file);
				if($time && time() - $time > 60 * 60 * 4){ // 4hr
					@unlink($this->chunkPath.$file);
				}
			}
			@closedir($handle);
		}
	}
	
	protected function checkChunks(){
		$files = [];
		$filesize = 0;
		for($i=0; $i<$this->chunkTotal; $i++){
			$fileName = $this->makeChunkFileName($i);
			$filePathName = $this->chunkPath.$fileName;
			if(!file_exists($filePathName)) return FALSE; // upload is still in progress, we can exit the loop
			$files[] = $filePathName;
			$filesize += filesize($filePathName);
		}
		
		// files are all present. check the combined file size.
		if($filesize < $this->fileSizeTotal){
			return FALSE; // upload is still in progress
		}
		
		// create final file and open for binary write
		if(!$out = @fopen($this->finalPath.$this->finalName, 'wb')){
			throw new \Exception('Failed to open output stream at '.$this->finalPath.$this->finalName);
		}
		
		foreach($files as $file){
			// binary read each chunk file we found from the earlier loop
			if(!$part = @fopen($file, 'rb')){
				@fclose($out);
				throw new \Exception('Failed to open file chunk '.$file);
			}
			// fwrite will append the data
			@fwrite($out, fread($part, filesize($file)));
			@fclose($part);
		}
		@fclose($out);
		
		// store reference to the final file
		$this->finalFile = new File($this->finalPath.$this->finalName, TRUE);
		
		// delete chunks
		foreach($files as $file){
			@unlink($file);
		}
		
		// garbage collection
		$this->cleanup();
		
		return TRUE;
	}
	
	protected function makeChunkFileName($chunkIndex):string{
		return $this->clientName.'.'.$this->dzuuid.'.'.$chunkIndex.$this->partFileExt; // foo_bar.pdf.UUID.0.part
	}
	
	protected function checkExistingFile(){
		if(!file_exists($this->finalPath.$this->finalFile)) return;
		$i=0;
		$sep = '-';
		$newFileName = $this->finalFile.$sep.$i;
		while(file_exists($this->finalPath.$newFileName)){
			$i++;
			$newFileName = $this->finalFile.$sep.$i;
		}
		$this->finalFile = $newFileName;
	}
}