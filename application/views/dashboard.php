<h2>Dashboard</h2>

<div id="usergames">

	<h3 id="user_games_header">My Games</h3>
	<table class="data" id="user_games_summary" cellpadding="0" cellspacing="0" border="0">
		<caption>My Current Games</caption>
		<thead>
			<tr>
				<th>Game</th><th>White vs. Black</th><th>Last Move</th>
			</tr>
		</thead>
		<tbody id="user_games_body">
		</tbody>
	</table>
	
	<a id="finished_games" class="mimicbutton" href="#">View Finished Games</a> &nbsp;&nbsp; <a id="open_games" class="mimicbutton" href="#">View Open Games</a>
	
	<h3 id="creategame">Create a Game <span class="frob_text"></span></h3>
	<form id="form_creategame" class="fancy" method="post" action="/game/createGame/">
		<fieldset class="floats main top">
			<label for="name">Game Name: </label> <input class="required validate-string" id="name" name="name" type="text" value="" /><span class="fieldnote" id="isNameAvailable"></span><br />
			<label for="opponent">Opponent: </label> <select id="opponent" name="opponent"><option value="0">Open Game (anyone may join)</option></select><br />
			<label for="playas">Play As: </label> <select id="playas" name="playas"><option value="1">White</option><option value="0">Black</option></select><br />
		</fieldset>
		<fieldset class="action">
			<input id="creategame-submit" name="creategame-submit" type="submit" value="Create" />
		</fieldset>
		<fieldset class="subaction bottom"></fieldset>
	</form><br class="clear" />

</div>

<div class="datalist" id="userinfo">
	<h3>My User Info</h3>
	<span class="label" id="label_username">Username</span> <span class="datafield" id="username"></span><br />

	<span class="label" id="label_email">Email</span> <span class="datafield" id="email"></span><a class="action" href="#" id="edit_userinfo_email">change</a><br />
	<form id="form_userinfo_email" method="post" class="one-line">
		<fieldset>
			<label for="input_email">New Email: </label> <input id="input_email" name="input_email" type="text" value="" /> <input id="email-submit" name="email-submit" type="button" value="Save" />
		</fieldset>
	</form><div class="clear"></div>

	<span class="label" id="label_password">Password</span> <a class="action" href="#" id="edit_userinfo_password">change</a><br />
	<form id="form_userinfo_password" method="post" class="one-line">
		<fieldset>
			<label for="input_password2">New Password: </label> <input id="input_password" name="input_password" type="password" value="" /><br />
			<label for="input_password2">New Password (again): </label> <input id="input_password2" name="input_password2" type="password" value="" /> <input id="password-submit" name="password-submit" type="button" value="Save" />
		</fieldset>
	</form><div class="clear"></div>

	<span class="label" id="label_notifications">Email Notifications</span> <span class="datafield" id="notifications"></span><a class="action" href="#" id="edit_userinfo_notifications">change</a><br />

	<span class="label" id="label_wins">Wins</span> <span class="datafield" id="wins"></span><br />
	<span class="label" id="label_losses">Losses</span> <span class="datafield" id="losses"></span><br />
	<span class="label" id="label_draws">Draws</span> <span class="datafield" id="draws"></span><br />
</div>

<br class="clear" />