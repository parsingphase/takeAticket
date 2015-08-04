/**
 * Created by wechsler on 04/08/15.
 */
var ticketer = {
    greet: function () {
        $('#target').text('Hi from ticketer');
    },

    go: function () {
        ticketer.reloadTickets();
        setInterval(ticketer.reloadTickets, 10000);
    },

    reloadTickets: function () {
        console.log('reloadTickets');
        //var tickets = [
        //    {title: 123},
        //    {title: 234},
        //    {title: 456}
        //];

        $.get('/next', function (tickets) {

            var out = '';
            for (var i = 0; i < tickets.length; i++) {
                var title = tickets[i].title;
                console.log(i + ': ' + title);
                out += '<div class="ticket well well-lg"><p class="text-center">' + title + '</p></div>';
            }
            out += Date.now().toLocaleString();

            $('#target').html(out);
        });
    }
};