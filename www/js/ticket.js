/**
 * Created by wechsler on 04/08/15.
 */
var ticketer = (function () {
    'use strict';
    //noinspection JSUnusedGlobalSymbols
    return {
        upcomingTicketTemplate: null,
        manageTemplate: null,
        songAutocompleteItemTemplate: null,
        addTicketTemplate: null,
        songDetailsTemplate: null,
        searchCount: 10,
        instrumentOrder: ['V', 'G', 'B', 'D', 'K'],

        /**
         * @var {{songInPreview,upcomingCount,iconMapHtml}}
         */
        displayOptions: {},

        /**
         * List of all performers (objects) who've signed up in this session
         */
        performers: [],

        performerExists: function (performerName) {
            for (var i = 0; i < this.performers.length; i++) {
                if (this.performers[i].performerName.toLowerCase() == performerName.toLowerCase()) {
                    return true;
                }
            }
            return false;
        },

        addPerformerByName: function (performerName) {
            this.performers.push({performerName: performerName});
            // now resort it
            this.performers.sort(function (a, b) {
                return a.performerName.localeCompare(b.performerName);
            });
        },

        /**
         * Run the "upcoming" panel
         */
        go: function () {
            this.initTemplates();

            ticketer.reloadTickets();
            setInterval(function () {
                //ticketer.reloadTickets();
            }, 10000);
        },

        /**
         * Draw an "upcoming" ticket
         * @param ticket {{band}}
         * @returns {*}
         */
        drawDisplayTicket: function (ticket) {
            // sort band into standard order
            var unsortedBand = ticket.band;
            var sortedBand = {};
            for (var i = 0; i < this.instrumentOrder.length; i++) {
                var instrument = this.instrumentOrder[i];
                if (unsortedBand.hasOwnProperty(instrument)) {
                    sortedBand[instrument] = unsortedBand[instrument];
                }
            }
            ticket.band = sortedBand;
            var ticketParams = {ticket: ticket, icons: this.displayOptions.iconMapHtml};
            console.log(['upcomingTicketTemplate', ticketParams]);
            return this.upcomingTicketTemplate(ticketParams);
        },

        /**
         * Draw a "queue management" ticket
         * @param ticket
         * @returns {*}
         */
        drawManageableTicket: function (ticket) {
            ticket.used = Number(ticket.used); // force int

            return this.manageTemplate({ticket: ticket});
        },

        /**
         * Reload all tickets on the upcoming page
         */
        reloadTickets: function () {
            var that = this;
            //console.log('reloadTickets');

            $.get('/api/next', function (tickets) {

                var out = '';
                for (var i = 0; i < tickets.length; i++) {
                    var ticket = tickets[i];
                    out += that.drawDisplayTicket(ticket);
                }
                //out += Date.now().toLocaleString();

                var target = $('#target');
                target.html(out);

                target.find('.auto-font').each(
                    function () {
                        var fixedWidth = $(this).data('fixed-assetwidth');
                        if (!fixedWidth) {
                            fixedWidth = 0;
                        }
                        fixedWidth = Number(fixedWidth);

                        var spaceUsedByText = (this.scrollWidth - fixedWidth);
                        var spaceAvailableForText = (this.clientWidth - fixedWidth);
                        var rawScale = Math.max(spaceUsedByText / spaceAvailableForText, 1);
                        var scale = 1.05 * rawScale;

                        if (fixedWidth) {
                            console.log({
                                'Full drawn width': this.scrollWidth,
                                'Available width': this.clientWidth,
                                'fixedWidth': fixedWidth,
                                'Drawn width used by text': spaceUsedByText,
                                'Available width usable': spaceAvailableForText,
                                rawScale: rawScale,
                                scale: scale,
                                contents: $(this).text()
                            });
                        }

                        // 1.05 extra scale to fit neatly, fixedWidth is non-scaling elements
                        var font = Number($(this).css('font-size').replace(/[^0-9]+$/, ''));
                        $(this).css('font-size', Number(font / scale).toFixed() + 'px');
                    }
                );

            });
        },

        /**
         * Enable queue management ticket buttons in the specified element
         * @param topElement
         */
        enableButtons: function (topElement) {
            var that = this;

            $(topElement).find('.performButton').click(function () {
                that.performButtonCallback(this);
            });

            $(topElement).find('.removeButton').click(function () {
                that.removeButtonCallback(this);
            });
        },

        enableSongSearchBox: function (songSearchInput, songSearchResultsTarget, songClickHandler) {
            var that = this;
            $(songSearchInput).keyup(
                function () {
                    var songComplete = $(songSearchResultsTarget);
                    var input = $(this);
                    var searchString = input.val();
                    //console.log('SS: ' + searchString);
                    if (searchString.length >= 3) {
                        //console.log('SS+: ' + searchString);
                        $.ajax({
                            method: 'POST',
                            data: {
                                searchString: searchString,
                                searchCount: that.searchCount
                            },
                            url: '/api/songSearch',
                            /**
                             * @param {{songs, searchString}} data
                             */
                            success: function (data) {
                                //console.log(['songSearch returned', data]);
                                var songs = data.songs;
                                if (input.val() == data.searchString) {
                                    // ensure autocomplete response is still valid for current input value
                                    var out = '';
                                    var song; // used twice below
                                    for (var i = 0; i < songs.length; i++) {
                                        song = songs[i];
                                        out += that.songAutocompleteItemTemplate({song: song});
                                    }
                                    songComplete.html(out).show();

                                    // now attach whole song as data:
                                    for (i = 0; i < songs.length; i++) {
                                        song = songs[i];
                                        var songId = song.id;
                                        songComplete.find('.acSong[data-song-id=' + songId + ']').data('song', song);
                                    }

                                    that.enableAcSongSelector(songComplete, songClickHandler);
                                }
                            },
                            error: function (xhr, status, error) {
                                //console.log('songSearch post ERROR: ' + status);
                                void(error);
                            }
                        });
                    } else {
                        songComplete.html('');
                    }
                }
            );
        },

        /**
         * Completely (re)generate the add ticket control panel and enable its controls
         *
         */
        resetAddTicketBlock: function () {
            var that = this;

            // Current panel state in function scope
            var selectedInstrument = 'V';
            var currentBand = {};

            var controlPanelOuter = $('.addTicketOuter');

            var nextInstrumentTab = function () {
                // find what offset we're at in instrumentOrder
                var currentOffset = 0;
                for (var i = 0; i < that.instrumentOrder.length; i++) {
                    if (that.instrumentOrder[i] == selectedInstrument) {
                        currentOffset = i;
                    }
                }
                var nextOffset = currentOffset + 1;
                if (nextOffset >= that.instrumentOrder.length) {
                    nextOffset = 0;
                }
                var instrument = that.instrumentOrder[nextOffset];
                selectedInstrument = instrument; // reset before we redraw tabs
                var newActiveTab = setActiveTab(instrument);

                //console.log(['nextInstrumentTab', instrument]);
                // make sure we switch to a *visible* tab
                if (newActiveTab.hasClass('instrumentUnused')) {
                    nextInstrumentTab();
                }
            };

            /**
             * (re)Draw the add ticket control panel in the .addTicketOuter element
             */
            var drawAddTicketForm = function () {
                controlPanelOuter.html(that.addTicketTemplate({performers: that.performers}));
                rebuildPerformerList(controlPanelOuter.find('.performers'));
            };

            var findPerformerInstrument = function (name) {
                var instrumentPlayers;
                for (var instrumentCode in currentBand) {
                    if (currentBand.hasOwnProperty(instrumentCode)) {
                        instrumentPlayers = currentBand[instrumentCode];
                        for (var i = 0; i < instrumentPlayers.length; i++) {
                            if (instrumentPlayers[i].toUpperCase() == name.toUpperCase()) {
                                return instrumentCode;
                            }
                        }
                    }
                }
                return null;
            };

            var rebuildPerformerList = function () {
                var newButton;
                var targetElement = controlPanelOuter.find('.performers');
                targetElement.text(''); // remove existing list

                var lastInitial = '';
                //console.log(['rebuildPerformerList', that.performers]);
                var performerCount = that.performers.length;
                var letterSpan;
                for (var pIdx = 0; pIdx < performerCount; pIdx++) {
                    var performerName = that.performers[pIdx].performerName;
                    var performerInstrument = findPerformerInstrument(performerName);
                    var isPerforming = performerInstrument ? 1 : 0;
                    var initialLetter = performerName.charAt(0).toUpperCase();
                    if (lastInitial !== initialLetter) {
                        if(letterSpan) {
                            targetElement.append(letterSpan);
                        }
                        letterSpan = $('<span class="letterSpan" />');
                        if ((performerCount > 15)) {
                            letterSpan.append($('<span class="initialLetter">' + initialLetter + '</span>'));
                        }
                    }
                    lastInitial = initialLetter;

                    newButton = $('<span></span>');
                    newButton.addClass('btn addPerformerButton');
                    newButton.addClass(isPerforming ? 'btn-primary' : 'btn-default');
                    if (isPerforming && (performerInstrument !== selectedInstrument)) { // dim out buttons for other instruments
                        newButton.attr('disabled', 'disabled');
                    }
                    newButton.text(performerName);
                    newButton.data('selected', isPerforming); // this is where it gets fun - check if user is in band!
                    letterSpan.append(newButton);
                }
                targetElement.append(letterSpan);

                // enable the new buttons
                $('.addPerformerButton').click(function () {
                    var name = $(this).text();
                    var selected = $(this).data('selected') ? 0 : 1; // reverse to get new state
                    if (selected) {
                        $(this).removeClass('btn-default').addClass('btn-primary');
                    } else {
                        $(this).removeClass('btn-primary').addClass('btn-default');
                    }
                    $(this).data('selected', selected); // toggle

                    //console.log('Clicked performer name: "' + name + '"');
                    alterInstrumentPerformerList(selectedInstrument, name, selected);
                });

            };

            // reset band to empty
            for (var instrumentIdx = 0; instrumentIdx < that.instrumentOrder.length; instrumentIdx++) {
                var instrument = that.instrumentOrder[instrumentIdx];
                currentBand[instrument] = []; // Store all instruments as arrays - most can only be single, but vocals is 1..n potentially
            }

            var addTicketCallback = function () {
                //var that = this;
                var titleInput = $('.addTicketTitle');
                var newTitle = titleInput.val();
                var songInput = $('.selectedSongId');
                var songId = songInput.val();
                //console.log('addTicket: ' + newTitle);
                var data = {
                    title: newTitle,
                    songId: songId,
                    band: currentBand
                };

                //console.log(['newTicket', data]);

                $.ajax({
                        method: 'POST',
                        data: data,
                        url: '/api/newTicket',
                        success: function (data, status) {
                            void(status);
                            titleInput.val('');
                            songInput.val('');
                            $('#target').append(that.drawManageableTicket(data.ticket));
                            var ticketId = data.ticket.id;
                            var ticketBlock = $('.ticket[data-ticket-id="' + ticketId + '"]');
                            that.enableButtons(ticketBlock);

                            if (data.performers) {
                                that.performers = data.performers;
                            }

                            that.resetAddTicketBlock();

                        },
                        error: function (xhr, status, error) {
                            void(error);
                            //console.log('addTicketTitle post ERROR: ' + status);
                        }
                    }
                );
            };

            drawAddTicketForm();

            // enable 'Add' button
            $('.addTicketButton').click(addTicketCallback);

            //enable the instrument tabs
            var allInstrumentTabs = controlPanelOuter.find('.instrument');

            var getTabByInstrument = function (instrument) {
                return controlPanelOuter.find('.instrument[data-instrument-shortcode=' + instrument + ']');
            };

            var setActiveTab = function (selectedInstrument) {
                allInstrumentTabs.removeClass('instrumentSelected');
                var selectedTab = getTabByInstrument(selectedInstrument);
                selectedTab.addClass('instrumentSelected');
                rebuildPerformerList(); // rebuild in current context
                return selectedTab;
            };

            allInstrumentTabs.click(
                function () {
                    selectedInstrument = $(this).data('instrumentShortcode');
                    setActiveTab(selectedInstrument);
                }
            );

            var ticketTitleInput = $('.addTicketTitle');

            var updateBandSummary = function () {
                var bandName = $('.addTicketTitle').val();
                var members = [];
                for (var instrument in currentBand) {
                    if (currentBand.hasOwnProperty(instrument)) {
                        for (var i = 0; i < currentBand[instrument].length; i++) {
                            members.push(currentBand[instrument][i]);
                        }
                    }
                }
                var memberList = members.join(', ');
                var summaryHtml = (bandName ? bandName + '<br />' : '') + memberList;
                $('.selectedBand').html(summaryHtml);
            };

            // Copy band name into summary area on Enter
            ticketTitleInput.keydown(function (e) {
                if (e.keyCode == 13) {
                    updateBandSummary();
                }
            });

            var alterInstrumentPerformerList = function (instrument, changedPerformer, isAdd) {
                //console.log(['alterPerformerList', instrument, changedPerformer, isAdd]);
                var selectedTab = controlPanelOuter.find('.instrument[data-instrument-shortcode=' + selectedInstrument + ']');
                var currentPerformerNameSpan = selectedTab.find('.instrumentPerformer');
                var currentInstrumentPerformers = currentBand[selectedInstrument];

                var newInstrumentPerformers = [];
                for (var i = 0; i < currentInstrumentPerformers.length; i++) {
                    var member = currentInstrumentPerformers[i].trim(); // trim only required when we draw data from manual input
                    if (member.length) {
                        if (member.toUpperCase() != changedPerformer.toUpperCase()) { // if it's not the name on our button, no change
                            newInstrumentPerformers.push(member);
                        }
                    }
                }

                if (isAdd) { // if we've just selected a new user, append them
                    newInstrumentPerformers.push(changedPerformer);
                    if (!that.performerExists(changedPerformer)) {
                        that.addPerformerByName(changedPerformer);
                    }
                }

                currentBand[selectedInstrument] = newInstrumentPerformers; // now update band with new performers of this instrument
                //console.log(['newInstrumentPerformers', newInstrumentPerformers]);

                rebuildPerformerList();

                //TODO Generate band name more intelligently, take both name & performers into account, probably don't sort
                var performerString = newInstrumentPerformers.sort().join(', ');
                if (!performerString) {
                    performerString = '<i>Needed</i>';
                }
                currentPerformerNameSpan.html(performerString);

                updateBandSummary();

                if (newInstrumentPerformers.length) { // if we've a performer for this instrument, skip to next
                    nextInstrumentTab();
                }

            };

            $('.newPerformer').keydown(function (e) {
                if (e.keyCode == 13) {
                    var newPerformerInput = $('.newPerformer');
                    var newName = newPerformerInput.val();
                    //console.log('Manually entered new name: ' + newName + ' for ' + selectedInstrument);
                    if (newName.trim().length) {
                        alterInstrumentPerformerList(selectedInstrument, newName, true);
                    }
                    newPerformerInput.val('');
                }
            });

            /**
             *
             * @param {{id, title, artist, hasKeys, hasHarmony}} song
             */
            var managePageSongSelectionClick = function (song) {
                var selectedId = song.id;
                var selectedSong = song.artist + ': ' + song.title;

                // perform actions with selected song
                var addTicketBlock = $('.addTicket');
                addTicketBlock.find('input.selectedSongId').val(selectedId);
                addTicketBlock.find('.selectedSong').text(selectedSong);
                //console.log(['selected song', song]);
                var keysTab = controlPanelOuter.find('.instrumentKeys');
                if (song.hasKeys) {
                    keysTab.removeClass('instrumentUnused');
                } else {
                    keysTab.addClass('instrumentUnused');
                    // also uncheck any performer for instrument (allow use elsewhere)
                    currentBand.K = [];
                    keysTab.find('.instrumentPerformer').html('<i>Needed</i>');
                    rebuildPerformerList();
                }
            };

            // set up the song search box in this control panel and set the appropriate callback
            var songSearchInput = '.addSongTitle';
            var songSearchResultsTarget = '.songComplete';

            this.enableSongSearchBox(songSearchInput, songSearchResultsTarget, managePageSongSelectionClick);
        },

        manage: function (tickets) {
            var that = this;
            this.initTemplates();
            //console.log(tickets);

            var out = '';
            for (var i = 0; i < tickets.length; i++) {
                var ticket = tickets[i];
                out += that.drawManageableTicket(ticket);
            }
            $('#target').html(out);

            var $sortContainer = $('.sortContainer');
            $sortContainer.sortable({
                axis: 'y',
                update: function (event, ui) {
                    void(event);
                    void(ui);
                    that.ticketOrderChanged();
                }
            }).disableSelection().css('cursor', 'move');

            this.enableButtons($sortContainer);

            this.resetAddTicketBlock();

        },

        initSearchPage: function () {
            var that = this;
            this.initTemplates();
            this.enableSongSearchBox('.searchString', '.songComplete', that.searchPageSongSelectionClick);
        },

        initTemplates: function () {
            var that = this;

            // commaList = each, with commas joining. Returns value at t as tuple {k,v}
            //TODO work out how to use options to more closely mimic 'each'
            Handlebars.registerHelper('commalist', function (context, options) {
                var retList = [];

                for (var key in context) {
                    if (context.hasOwnProperty(key)) {
                        retList.push(options.fn({k: key, v: context[key]}));
                    }
                }

                return retList.join(', ');
            });

            Handlebars.registerHelper('instrumentIcon', function (instrumentCode) {
                var icon = '<span class="instrumentTextIcon">' + instrumentCode + '</span>';
                if (that.displayOptions.hasOwnProperty('iconMapHtml')) {
                    if (that.displayOptions.iconMapHtml.hasOwnProperty(instrumentCode)) {
                        icon = that.displayOptions.iconMapHtml[instrumentCode];
                    }
                }
                return new Handlebars.SafeString(icon);
            });

            this.manageTemplate = Handlebars.compile(
                '<div class="ticket well well-sm {{#if ticket.used}}used{{/if}}" data-ticket-id="{{ ticket.id }}">' +
                '        <div class="pull-right">' +
                '        <button class="btn btn-primary performButton" data-ticket-id="{{ ticket.id }}">Performing</button>' +
                '        <button class="btn btn-danger removeButton" data-ticket-id="{{ ticket.id }}">Remove</button>' +
                '        </div>' +
                '        <div class="ticketId">#{{ ticket.id }}:</div> ' +
                '<div class="pendingSong">' +
                '<span class="fa fa-group"></span> ' +

                    // Display performers with metadata if valid, else just the band title.
                '{{#if ticket.performers}}' +
                '{{#each ticket.performers}}' +
                '<span class="performer"> {{performerName}} ({{songsDone}}/{{songsPending}}) </span>' +
                '{{/each}}' +
                '{{else}}' +
                '{{ ticket.title }}' +
                '{{/if}}' +

                '{{#if ticket.used}} (done){{/if}}' +
                '{{#if ticket.song}}<br /><span class="fa fa-music"></span> {{ticket.song.artist}}: ' +
                '{{ticket.song.title}}' +
                '{{/if}}' +
                '</div>' +
                '</div>'
            );

            //var block = '<div class="ticket well"><p class="text-center">' + title + '</p></div>';

            //noinspection JSUnresolvedVariable
            this.upcomingTicketTemplate = Handlebars.compile(
                '<div class="ticket well ' +
                (this.displayOptions.songInPreview ? 'withSong' : 'noSong') +
                ' ' +
                (this.displayOptions.title ? 'withTitle' : 'noTitle') +
                '" data-ticket-id="{{ ticket.id }}">' +
                '  <div class="ticket-inner">' +
                '    <p class="text-center band auto-font">{{ticket.title}}</p>' +
                '    <p class="performers auto-font" data-fixed-assetwidth="200">' +
                '{{#each ticket.band}}' +
                '<span class="instrumentTag">{{instrumentIcon @key}}</span>' +
                '<span class="instrumentPerformers">{{#commalist this}}{{v.performerName}}{{/commalist}}</span>' +
                '{{/each}}' +
                '    </p>' +
                (this.displayOptions.songInPreview ? '{{#if ticket.song}}<p class="text-center song auto-font">{{ticket.song.artist}}: {{ticket.song.title}}</p>{{/if}}' : '') +
                '        </div>' +
                '</div>  '
            );

            this.songAutocompleteItemTemplate = Handlebars.compile(
                '<div class="acSong" data-song-id="{{ song.id }}">' +
                '        <div class="acSong-inner">' +
                '        {{song.artist}}: {{song.title}}' +
                '        </div>' +
                '</div>  '
            );

            this.addTicketTemplate = Handlebars.compile(
                '<div class="addTicket well">' +
                '<div class="pull-right">' +
                '<button class="addTicketButton btn btn-success">Add</button>' +
                '</div>' +

                '<h3>Add new ticket</h3>' +

                '<div class="addTicketInner">' +
                '<div class="addTicketSong">' +
                '<div class="ticketAspectSummary"><span class="fa fa-music fa-2x" title="Song"></span>' +
                '<input type="hidden" class="selectedSongId"/> <span class="selectedSong"></span>' +
                '</div>' +
                '<div class="input-group input-group">' +
                '<span class="input-group-addon" id="search-addon1"><span class="fa fa-search"></span> </span>' +
                '<input class="addSongTitle form-control" placeholder="Search song or use code"/>' +
                '</div>' +

                '<div class="songCompleteOuter">' +
                '<div class="songComplete"></div>' +
                '</div>' + // /songCompleteOuter
                '</div>' + // /addTicketSong

                '<div class="addTicketBandColumn">' +

                '<div class="ticketAspectSummary"><span class="fa fa-group fa-2x pull-left" title="Performers"></span>' +
                '<span class="selectedBand"></span>' +
                '</div>' + // /ticketAspectSummary

                '<div class="input-group">' +
                '<span class="input-group-addon" id="group-addon-band"><span class="fa fa-pencil"></span> </span>' +
                '<input class="addTicketTitle form-control" placeholder="Band name (optional)"/>' +
                '</div>' + // /input-group

                '<div class="bandControls">' +
                '<div class="bandTabsOuter">' +
                '<div class="instruments">' +
                ' <div class="instrument instrumentVocals instrumentSelected" data-instrument-shortcode="V">' +
                '  <div class="instrumentName">Vocals</div>' +
                '  <div class="instrumentPerformer"><i>Needed</i></div>' +
                ' </div>' +
                ' <div class="instrument instrumentGuitar" data-instrument-shortcode="G">' +
                '  <div class="instrumentName">Guitar</div>' +
                '  <div class="instrumentPerformer"><i>Needed</i></div>' +
                ' </div>' +
                ' <div class="instrument instrumentBass" data-instrument-shortcode="B">' +
                '  <div class="instrumentName">Bass</div>' +
                '  <div class="instrumentPerformer"><i>Needed</i></div>' +
                ' </div>' +
                ' <div class="instrument instrumentDrums" data-instrument-shortcode="D">' +
                '  <div class="instrumentName">Drums</div>' +
                '  <div class="instrumentPerformer"><i>Needed</i></div>' +
                ' </div>' +
                ' <div class="instrument instrumentKeys instrumentUnused" data-instrument-shortcode="K">' +
                '  <div class="instrumentName">Keyboard</div>' +
                '  <div class="instrumentPerformer"><i>Needed</i></div>' +
                ' </div>' +
                '</div>' + // /instruments
                '<div class="performerSelect">' +
                    //'<div class="ticketAspectSummary">' +
                    //'<span class="selectedBand"></span>' +
                    //'</div>' +
                '<div class="input-group input-group">' +
                '<span class="input-group-addon" id="group-addon-performer"><span class="fa fa-plus"></span> </span>' +
                '<input class="newPerformer form-control" placeholder="New performer (Firstname Initial)"/>' +
                '</div>' +

                '<div class="performers"></div>' +
                '</div>' + // /performerSelect
                '</div>' + // /bandTabsOuter
                '</div>' + // /bandControls
                '</div>' + // /addTicketBandColumn
                '<div class="clearfix"></div>' + // clear after addTicketBandColumn
                '</div>' + // /addTicketInner
                '</div>' // /addTicket
            );

            this.songDetailsTemplate = Handlebars.compile(
                '<div class="songDetails"><h3>{{song.artist}}: {{song.title}}</h3>' +
                'Code: {{song.codeNumber}}<br /> ' +
                'Harmony? {{#if song.hasHarmony}}Yes{{else}}No{{/if}}<br /> ' +
                'Keys? {{#if song.hasKeys}}Yes{{else}}No{{/if}}<br /> ' +
                'Source: {{song.source}}' +
                '</div>'
            );

        },

        ticketOrderChanged: function () {
            //console.log('Ticket order changed');
            var idOrder = [];
            $('#target').find('.ticket').each(
                function () {
                    var ticketBlock = $(this);
                    //console.log(ticketBlock.data());
                    var ticketId = ticketBlock.data('ticketId');
                    //console.log('Id: ' + ticketId);
                    idOrder.push(ticketId);
                }
            );

            $.ajax({
                method: 'POST',
                data: {
                    idOrder: idOrder
                },
                url: '/api/newOrder',
                success: function (data, status) {
                    //console.log('ticketOrderChanged post OK: ' + status);
                },
                error: function (xhr, status, error) {
                    //console.log('ticketOrderChanged post ERROR: ' + status);
                    void(error);
                }
            });
        },

        performButtonCallback: function (button) {
            //var that = this;

            button = $(button);
            var ticketId = button.data('ticketId');
            //console.log('performButtonCallback: ' + ticketId);
            $.ajax({
                    method: 'POST',
                    data: {
                        ticketId: ticketId
                    },
                    url: '/api/useTicket',
                    success: function (data, status) {
                        void(data);
                        void(status);
                        var ticketBlock = $('.ticket[data-ticket-id="' + ticketId + '"]');
                        ticketBlock.addClass('used');
                        ticketBlock.append(' (done)');
                    },
                    error: function (xhr, status, error) {
                        void(xhr);
                        void(error);
                        //console.log('performButtonCallback post ERROR: ' + status);
                    }
                }
            );
        },

        removeButtonCallback: function (button) {
            button = $(button);
            var ticketId = button.data('ticketId');
            //console.log('removeButtonCallback: ' + ticketId);
            $.ajax({
                    method: 'POST',
                    data: {
                        ticketId: ticketId
                    },
                    url: '/api/deleteTicket',
                    success: function (data, status) {
                        void(status);
                        var ticketBlock = $('.ticket[data-ticket-id="' + ticketId + '"]');
                        ticketBlock.remove();
                    },
                    error: function (xhr, status, error) {
                        void(error);
                        //console.log('removeButtonCallback post ERROR: ' + status);
                    }
                }
            );
        },

        enableAcSongSelector: function (outerElement, songClickHandler) {
            var that = this;
            outerElement.find('.acSong').click(
                function () {
                    // find & decorate clicked element
                    outerElement.find('.acSong').removeClass('selected');
                    $(this).addClass('selected');

                    var song = $(this).data('song');
                    songClickHandler.call(that, song); // run in 'that' context
                }
            );
        },


        searchPageSongSelectionClick: function (song) {
            var target = $('#searchTarget');
            target.html(this.songDetailsTemplate({song: song}));
        },

        dumpSongInfo: function (song) {
            console.log(['song', song]);
        }
    };
}());