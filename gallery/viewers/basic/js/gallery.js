/**
 * Gallery controller class to control retrieval of gallery information and creating
 * view models
 * @param {*} webserviceUrl - base URL to call gallery webservices
 */
var gallery;
$(function() {
    function Gallery(webserviceUrl) {
        var templatePanelUrl = 'viewers/basic/templates/template_panel.html';
        var templateFolderUrl = 'viewers/basic/templates/template_folder.html';
        var templateFileUrl = 'viewers/basic/templates/template_file.html';
        var templateViewerUrl = 'viewers/basic/templates/template_viewer.html';
    
        var templatePanel, templateFolder, templateFile, templateViewer;
    
        var me = this;
    
        /**
         * Launch the gallery, retrieve templates, and initialize gallery data, 
         * optionally for the folder location specified after the hash tag
         */
        this.launch = function() {
            var startAt = '/';
            var hash = window.location.hash;
            if(hash && hash.length > 1) {
                if(hash[0] == '#') startAt = hash.substr(1, hash.length - 1);
            }
    
            $.when($.ajax(templatePanelUrl), $.ajax(templateFolderUrl), $.ajax(templateFileUrl), $.ajax(templateViewerUrl))
                .done(function (t1, t2, t3, t4) {
                    templatePanel = t1[0];
                    templateFolder = t2[0];
                    templateFile = t3[0];
                    templateViewer = t4[0];
                    $("body").append(templateViewer);
                    me.updateGallery(startAt);
                })
                .fail(function(err) {
                    window.alert('Error: ' + err.statusText);
                });
        }
    
        /**
         * Select the gallery folder
         */
        this.selectFolder = function(dataUrl) {
            var url = decodeURIComponent(dataUrl);
            me.updateGallery(url);
        };
    
        /**
         * Update the displayed gallery to show the specified location, render
         * the templates using retrieved data
         */
        this.updateGallery = function(location) {
            $("#gallery-busy").css('visibility', 'visible');
            $.when($.ajax(webserviceUrl + '/info' + location))
                .done(function(results) {
                    var parents = [];
                    var parent = results.parent;
                    while(parent) {
                        parents.unshift(parent);
                        parent = parent.parent;
                    }
                    results.parents = parents;
                    panelContent = Mustache.render(templatePanel, results);
                    var panel = $(panelContent);
                    var panelItems = panel.first("#gallery-items");
                    if(panelItems.length == 0) {
                        window.alert('Gallery panel requres child with gallery-items item');
                        return;
                    }
    
                    var thumbnailQueue = [];
                    if(results.children) {
                        for(var i in results.children) {
                            var child = results.children[i];
                            var hasThumbnail = child.thumbnailUrl != null;
                            if(! hasThumbnail) {
                                child.thumbnailUrl = 'viewers/basic/css/thumbnail-loading.gif';
                            }
                            var item = null;
                            switch(child.type) {
                                case 'directory':
                                    item = $(Mustache.render(templateFolder, child));
                                    break;
                                case 'file':
                                    item = $(Mustache.render(templateFile, child));
                            }
    
                            if(item) {
                                if(! hasThumbnail) {
                                    thumbnailQueue.push({
                                        location: child.type == 'directory' ? child.dataUrl : child.imageUrl,
                                        item: item
                                    });
                                }
                                panelItems.append(item);
                            }
                        }
                    }
    
                    var div = $("#gallery");
                    div.empty().append(panel);
                    $("#gallery-busy").css('visibility', 'hidden');
    
                    if(thumbnailQueue.length > 0) {
                        me.processThumbnailQueue(thumbnailQueue, 0);
                    }
                })
                .fail(function(err) {
                    $("#gallery-busy").css('visibility', 'hidden');
                    window.alert('Error: ' + err.statusText);
                });
        }
    
        this.processThumbnailQueue = function(queue, index) {
            if(index >= queue.length) {
                return;
            }
            var location = queue[index].location;
            var item = queue[index].item;
            $.when($.ajax(webserviceUrl + '/thumbnail/' + location))
                .done(function(result) {
                    var url = result.thumbnailUrl;
                    if(url) {
                        item.children(".gallery-thumbnail").css("background-image", "url('" + url + "')");
                    }
                    me.processThumbnailQueue(queue, index + 1);
                })
                .fail(function(err) {
                    msg = err.ErrorMessage ? err.ErrorMessage : err.statusText;
                    window.alert('Unable to generate thumbnail: ' + msg);
                });
        };
    
        this.showViewer = function(imageUrl, filename) {
            $("#gallery-viewer .gallery-downbtn")
                .attr("download", filename)
                .attr("href", imageUrl);
            $("#gallery-viewer img").attr('src', imageUrl)
                .css("max-width", '100%')
                .css("max-height", $(window).height() * 0.7);
            $("#gallery").hide();
            $("#gallery-viewer").css("width", "100%");
            $("#gallery-viewer").show();
        }
    
        this.hideViewer = function() {
            $("#gallery-viewer").hide();
            $("#gallery").show();
        }
    }

    gallery = (new Gallery('webservice'));
    gallery.launch();
})();
