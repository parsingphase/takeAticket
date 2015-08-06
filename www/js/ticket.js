/**
 * Created by wechsler on 04/08/15.
 */
var ticketer = {
    drawTemplate: null,
    manageTemplate: null,

    greet: function () {
        $('#target').text('Hi from ticketer');
    },

    go: function () {
        ticketer.reloadTickets();
        setInterval(ticketer.reloadTickets, 10000);
    },

    drawDisplayTicket: function (ticket) {
        var title = ticket.title;
        var block = '<div class="ticket well well-lg"><p class="text-center">' + title + '</p></div>';
        return block;
    },

    drawManagableTicket: function (ticket) {
        var title = ticket.title;
        //var block = '<div class="ticket well well-lg"><p class="text-center">' + title + '</p></div>';
        var block = this.manageTemplate({ticket: ticket});
        return block;

    },

    reloadTickets: function () {

        var that = this;
        console.log('reloadTickets');

        $.get('/api/next', function (tickets) {

            var out = '';
            for (var i = 0; i < tickets.length; i++) {
                var ticket = tickets[i];
                var block = that.drawDisplayTicket(ticket);
                out += block;
            }
            out += Date.now().toLocaleString();

            $('#target').html(out);
        });
    },

    manage: function (tickets) {
        this.initTemplates();
        console.log(tickets);
        var that = this;

        var out = '';
        for (var i = 0; i < tickets.length; i++) {
            var ticket = tickets[i];
            var block = that.drawManagableTicket(ticket);
            out += block;
        }
        $('#target').html(out);


        $('.sortContainer').sortable({axis: 'y'}).disableSelection().css('cursor', 'move');
        $('.addTicketButton').click(function () {
            var titleInput = $('.addTicketTitle');
            var newTitle = titleInput.val();
            console.log('addTicket: ' + newTitle);
            $.ajax({
                    method: 'POST',
                    data: {
                        title: newTitle
                    },
                    url: '/api/newTicket',
                    success: function (data, status) {
                        titleInput.val('');
                        $('#target').append(that.drawManagableTicket(data.ticket));
                    },
                    error: function (xhr, status, error) {
                        console.log('addTicketTitle post ERROR: ' + status);
                    }
                }
            );
        });
    },

    initTemplates: function () {
        this.manageTemplate = Handlebars.compile(
            '<div class="ticket well" data-ticket-title="{{ ticket.title }}">' +
            '        <div class="pull-right">' +
            '        <button class="btn btn-primary">Performing</button>' +
            '        <button class="btn btn-danger">Remove</button>' +
            '        </div>' +
            '        {{ ticket.offset }}: ' +
            '{{ ticket.title }}' +
            '</div>  '
        );
    }

};