import 'jquery';
import 'dropzone';

let dzOptions = {
	url: 'uploadfile/chunk',
	paramName: 'userfile',
	timeout: 180000, // needed when chunking, otherwise it'll just hang forever
	chunking: true,
	forceChunking: true,
	parallelUploads: 1,
	parallelChunkUploads: true,
	chunkSize: 1000000,  // chunk size 1,000,000 bytes (~1MB)
	retryChunks: true,
	retryChunksLimit: 3,
	addRemoveLinks: false,
	maxFilesize: null,
	acceptedFiles: null,
	chunksUploaded: (file, done) => {
		// tell CI to merge all the chunks
		$.ajax({
			method:'POST',
			url: 'uploadfile/assemble',
			data: {
				filename: file.name,
				dzuuid: file.upload.uuid,
				dztotalfilesize: file.size,
				dztotalchunkcount: file.upload.totalChunkCount,
			},
			success: (data) => { 
				done(); // must call done function
				$('.js-uploads').prepend(`<img style="max-width:500px;" src="${data.file}" alt="">`);
			},
			error: (msg) => {
				file.accepted = false;
				// tell CI to delete chunks
				$.ajax({
					method:'POST',
					url: 'uploadfile/delete',
					data: {
						filename: file.name,
						dzuuid: file.upload.uuid,
						dztotalfilesize: file.size,
						dztotalchunkcount: file.upload.totalChunkCount,
					}
				});
			}
		});
	},
};
let dropzone = new Dropzone($('.js-dropzone').get(0), dzOptions);
dropzone.on('error', (file, errorMessage) => {
	// tell CI to delete chunks
	$.ajax({
		method:'POST',
		url: 'uploadfile/delete',
		data: {
			filename: file.name,
			dzuuid: file.upload.uuid,
			dztotalfilesize: file.size,
			dztotalchunkcount: file.upload.totalChunkCount,
		},
	});
});