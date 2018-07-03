<div class="round">
    <h4>{translate key="plugins.generic.charlesworth.archive.round"} {$venderRound} </h4>
    <table class="info fancy">
        <tbody>		
            <tr>
                <th>
                    &nbsp;
                </th>
                <th>
                    Date
                </th>
                <th>

                    {translate key="plugins.generic.charlesworth.archive.download"}

                </th>
            </tr>
            <tr>
                <th>

                    {translate key="plugins.generic.charlesworth.archive.sent"}

                </th>
                <td>
                    {$ceRoundSentDate}
                </td>
                <td>
                    {if $ceRoundSentArchiveURL}
                        <a href="{$ceRoundSentArchiveURL}">{translate key='plugins.generic.charlesworth.archive.download'}</a>
                    {else} {translate key="plugins.generic.charlesworth.pending"}
                    {/if}
                </td>
            </tr>
            <tr>
                <th>
                    {translate key="plugins.generic.charlesworth.archive.received"}
                </th>

                <td>
                    {$ceRoundReceiveDate}
                </td>
                <td>
                    {$receivedArchiveLink}
                </td>


            </tr>
            <tr>
                <th>

                    {translate key="plugins.generic.charlesworth.import.xml"}

                </th>
                <td colspan="2">
                    {if $xmlImportResult} {translate key="plugins.generic.charlesworth.xml.import.article.update.succeeded"}
                    {else}{translate key="plugins.generic.charlesworth.xml.import.article.update.succeeded"}
                    {/if}
                </td>
            </tr>					
            <tr>
                <th colspan="3">
                    {translate key="plugins.generic.charlesworth.import.files"}
                </th>
            </tr>
            <tr>
                <th>
                    Title
                </th>
                <th>
                    Type
                </th>
                <th>
                    Name
                </th>
            </tr>

            {** Right here is where file list goes *}
            {foreach from=$importedFiles item=file}
                <tr>
                    <td>
                        {if $file.fileTitle}{$fileTitle}
                        {else} {translate key="plugins.generic.charlesworth.na"}
                        {/if}
                    </td>
                    <td>
                        {if $file.type}{$fileType}
                        {else} {translate key="plugins.generic.charlesworth.na"}
                        {/if}
                    </td>
                    <td>
                        {if $file.file_name}<a href="{$downloadBaseUrl}/{$fileID}">{$fileName}</a>
                        {else} {translate key="plugins.generic.charlesworth.na"}
                        {/if}
                    </td>
                </tr>
            {/foreach}
            {** END file list *}

        </tbody>
    </table>
    {translate key="plugins.generic.charlesworth.debriefing"}
</div>
