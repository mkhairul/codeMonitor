
// Use Parse.Cloud.define to define as many cloud functions as you want.
// For example:
Parse.Cloud.define("hello", function(request, response) {
  Parse.Config.get().then(function(config){
  
        // Insert the message type
        var pushUrl = config.get('pushUrl');
        var notify = function(msg){
            var req = {
                    method: 'POST',
                    url: pushUrl,
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: {
                        "data": msg
                    },
                    success: function(data){
                        response.success(data);
                    },
                    error: function(error){
                        response.error(error);
                    }
            }
            Parse.Cloud.httpRequest(req);
        }
        notify('test');
    });
});


Parse.Cloud.afterSave("MonSession", function(request){
  Parse.Config.get().then(function(config){
    
        // Insert the message type
        var obj = request.object;
        
        Parse.Cloud.useMasterKey();
        var pushUrl = config.get('pushUrl');
        var notify = function(msg){
            var req = {
                    method: 'POST',
                    url: pushUrl,
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: {
                        "data": msg,
                        "type": "session"
                    },
                    success: function(data){
                        console.log('success');
                        console.log(data.text);
                    },
                    error: function(error){
                        console.log('error');
                        console.log(error);
                    }
            }
            Parse.Cloud.httpRequest(req);
        }
        notify(obj);
    });
});

Parse.Cloud.afterSave("FileChanges", function(request){
  Parse.Config.get().then(function(config){
    
        // Insert the message type
        var obj = request.object;
        
        Parse.Cloud.useMasterKey();
        var pushUrl = config.get('pushUrl');
        var notify = function(msg){
            var req = {
                    method: 'POST',
                    url: pushUrl,
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: {
                        "data": msg,
                        "type": "changes"
                    },
                    success: function(data){
                        response.success(data);
                    },
                    error: function(error){
                        response.error('something is wrong');
                    }
            }
            Parse.Cloud.httpRequest(req);
        }
        notify(obj);
    });
});