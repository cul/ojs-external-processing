{**
 * settingsForm.tpl
 *
 *
 * Charlesworth plugin settings
 *
 * $Id$
 *}
{strip}
{assign var="pageTitle" value="plugins.generic.charlesworth.settings"}
{include file="common/header.tpl"}
{/strip}
<div id="charlesworthSettings">
<div id="description">{translate key="plugins.generic.charlesworth.settings.description"}</div>

<div class="separator"></div>

<br />

<form method="post" action="{plugin_url path="settings"}">
{include file="common/formErrors.tpl"}

<table width="100%" class="data">
	<tr valign="top">
		<td width="20%" class="label">{fieldLabel name="ftphost" key="plugins.generic.charlesworth.ftphost"}</td>
		<td width="80%" class="value"><input type="text" id="ftphost" name="ftphost" value="{$ftphost|escape}" /></td>
	</tr>
	<tr valign="top">
		<td width="20%" class="label">{fieldLabel name="ftpuser" key="plugins.generic.charlesworth.ftpuser"}</td>
		<td width="80%" class="value"><input type="text" id="ftpuser" name="ftpuser" value="{$ftpuser|escape}" /></td>
	</tr>
	<tr valign="top">
		<td width="20%" class="label">{fieldLabel name="ftppassword" key="plugins.generic.charlesworth.ftppassword"}</td>
		<td width="80%" class="value"><input type="password" id="ftppassword" name="ftppassword" value="{$ftppassword|escape}" /></td>
	</tr>
	<tr valign="top">
		<td width="20%" class="label">{fieldLabel name="ftpdirectorysend" key="plugins.generic.charlesworth.ftpdirectorysend"}</td>
		<td width="80%" class="value"><input type="text" id="ftpdirectorysend" name="ftpdirectorysend" value="{$ftpdirectorysend|escape}" /></td>
	</tr>
	<tr valign="top">
		<td width="20%" class="label">{fieldLabel name="ftpdirectoryreceive" key="plugins.generic.charlesworth.ftpdirectoryreceive"}</td>
		<td width="80%" class="value"><input type="text" id="ftpdirectoryreceive" name="ftpdirectoryreceive" value="{$ftpdirectoryreceive|escape}" /></td>
	</tr>
	<tr valign="top">
		<td width="20%" class="label">{fieldLabel name="sendarticlefields" key="plugins.generic.charlesworth.sendarticlefields"}</td>
		<td width="80%" class="value">
			<textarea id="sendarticlefields" name="sendarticlefields" cols="60" rows="10">{$sendarticlefields|escape}</textarea>
			<br/>
			<span class="instruct">{translate key="plugins.generic.charlesworth.sendarticlefields.description"}</span>
		</td>
	</tr>
	<tr valign="top">
		<td width="20%" class="label">{fieldLabel name="mkdirmode" key="plugins.generic.charlesworth.mkdirmode"}</td>
		<td width="80%" class="value"><input type="text" id="mkdirmode" name="mkdirmode" value="{$mkdirmode|escape}" /></td>
	</tr>
	<tr valign="top">
		<td width="20%" class="label">{fieldLabel name="mkdirchgrp" key="plugins.generic.charlesworth.mkdirchgrp"}</td>
		<td width="80%" class="value"><input type="text" id="mkdirchgrp" name="mkdirchgrp" value="{$mkdirchgrp|escape}" /></td>
	</tr>
</table>

<br/>

<input type="submit" name="save" class="button defaultButton" value="{translate key="common.save"}"/><input type="button" class="button" value="{translate key="common.cancel"}" onclick="history.go(-1)"/>
</form>

<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</div>
{include file="common/footer.tpl"}
