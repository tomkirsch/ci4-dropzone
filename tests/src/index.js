import 'jquery';
import 'dropzone';

let dzFactory = function(target, myOptions){
	let dropzone; // create the variable before we create the options object for hositing
	let defaults = {
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
	};
	let options = {};
	Object.assign(options, defaults);
	Object.assign(options, myOptions);
	
	let deleteChunks = (file) => {
		let chunkCount = 1;
		if(file.upload.totalChunkCount != undefined){
			chunkCount = file.upload.totalChunkCount;
		}
		$.ajax({
			method:'POST',
			url: 'uploadfile/delete',
			data: {
				clientName: file.name,
				dzuuid: file.upload.uuid,
				dztotalfilesize: file.size,
				dztotalchunkcount: chunkCount,
			},
		});
	};
	
	options.chunksUploaded = (file, done) => {
		// we must save the total chunk count for error callback
		let chunkCount = file.upload.totalChunkCount;
		// tell CI to merge all the chunks
		$.ajax({
			method:'POST',
			url: 'uploadfile/assemble',
			data: {
				clientName: file.name,
				dzuuid: file.upload.uuid,
				dztotalfilesize: file.size,
				dztotalchunkcount: file.upload.totalChunkCount,
			},
		}).fail((xhr, status, error) => {
			file.accepted = false;
			// use hoisted variable since there is no "this". not sure how to do this more elegantly...
			if(dropzone){
				// this will trigger error and chunks will be deleted via ajax
				dropzone._errorProcessing([file], 'Assembly error: '+xhr.statusText, xhr);
			}
		}).done(data => {
			done();
			let $el = $(`<a href="${data.filePath}" target="_blank"/>`);
			if(data.isImage){
				$el.append(`<img style="max-width:300px;height:auto;" src="${data.filePath}" ${data.size_str} alt="">`);
			}else{
				$el.text(data.clientName);
			}
			$('.js-uploads').prepend($el);
			file.previewElement.remove();
		});
	};
	dropzone = new Dropzone(target, options);
	
	dropzone.on('error', (file, errorMessage) => {
		// CI will return an error object - use the message key to display it to the user
		if(typeof(errorMessage) === 'object'){
			dropzone._errorProcessing([file], errorMessage.message);
		}
		deleteChunks(file);
	});
	return dropzone;
}

Dropzone.autoDiscover = false; // so we can add the CSS class without auto-instantiation
let dropzone = dzFactory($('.js-dropzone').addClass('dropzone').get(0), {
	url: 'uploadfile/chunk',
})
