/*
  FastSSE v0.1.1

  Creative GP
  2018/07/29 (yyyy/mm/dd)
*/
'use strict';

class Stream {
    constructor(sender, receiver, distributor) {
	this.es = new EventSource(sender);

	this.receiver = receiver;
	this.distributor = distributor;

	this.Base64 = {
	    encode: function (str) {
		return btoa(unescape(encodeURIComponent(str)));
	    },
	    decode: function (str) {
		return decodeURIComponent(escape(atob(str)));
	    }
	};

	this.taginfo = {};

	{
	    let self = this;
	    this.es.addEventListener('server', function (e) {
		self.id = e.data;
	    }, false);
	}
    }

    send(data, tags) {
	//const tags = tags.join(',');
	fetch(`${this.receiver}?id=${this.id}&d=${data}&t=${tags}`);
    }

    ask_tag(tags, pass) {
	let cloj = function (self, tags, pass, callback) {
	    return function () { return callback(this.responseText, self, tags, pass); }; 
	};
	let onload = function (text, self, atags, apass) {
	    if (text == "") {
		const tags = atags.split(',');
		const pass = apass.split(',');
		for (let i=0, len=tags.length; i < len; ++i) {
		    self.taginfo[tags[i]] = pass[i];
		}
	    }
	};

	let xhr = new XMLHttpRequest();
	xhr.addEventListener('load', cloj(this, tags, pass, onload));
	xhr.open('GET', `${this.distributor}?q=join&id=${this.id}&t=${tags}&p=${pass}`);
	xhr.send();
    }

    add_tag(tags, pass) {
	fetch(`${this.distributor}?q=add&id=${this.id}&t=${tags}&p=${pass}`);
    }

    set OnMessage(func) {
	// Decode newlines and call `func`
	this.onMessage = e => func(e, e.data.replace(/%\\n/g, "\n"));
	this.es.addEventListener('d', this.onMessage);
    }

    set OnError(func) {
	this.onError = func;
	this.es.onerror = this.onError;
    }
}
