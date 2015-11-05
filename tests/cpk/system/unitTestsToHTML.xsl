<?xml version="1.0"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

	<xsl:template match="/">
		<h3>Results</h3>
			<table class='table table-bordered' id='mainResults' data-toggle="table" data-height="299">
				<thead>
					<tr>
						<th bgcolor='337ab7'>Tests</th>
						<th bgcolor='5cb85c'>Assertions</th>
						<th bgcolor='f0ad4e'>Failures</th>
						<th bgcolor='d9534f'>Errors</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><xsl:value-of select="testsuites/testsuite/@tests"/></td>
						<td><xsl:value-of select="testsuites/testsuite/@assertions"/></td>
						<td><xsl:value-of select="testsuites/testsuite/@failures"/></td>
						<td><xsl:value-of select="testsuites/testsuite/@errors"/></td>
					</tr>
				</tbody>
			</table>
		<h3>Tests</h3>
		<table class='table' id='results' data-toggle="table" data-height="299">
			<thead>
				<tr>
					<th>Part</th>
					<th>File</th>
					<th>Test</th>
					<th>Assertions</th>
					<th>Status</th>
					<th>Time</th>
				</tr>
			</thead>
			<tbody>
				<xsl:for-each select="testsuites/testsuite/testsuite/testcase">
   	    	 		<tr>
   		       			<td><xsl:value-of select="@class"/></td>
          				<td><xsl:value-of select="@file"/>:<xsl:value-of select="@line"/></td>
          				<td><xsl:value-of select="@name"/></td>
  	        			<td><xsl:value-of select="@assertions"/></td>
          				<!--<td><span class="label label-danger"><xsl:value-of select="@failures"/></span></td>-->
                        <xsl:if test="./failure">
                            <td>
                                <span class="label label-danger">
                                    failed
                                </span>
                            </td>
                        </xsl:if>
                         <xsl:if test="./error">
                            <td>
                                <span class="label label-danger">
                                    error
                                </span>
                            </td>
                        </xsl:if>
                        <xsl:if test="not(./failure)">
                        	<xsl:if test="not(./error)">
                            	<td>
                                	<span class="label label-success">
                                 	   success
                                	</span>
                            	</td>
                            </xsl:if>
                        </xsl:if>
          				<td><xsl:value-of select="@time"/></td>
        			</tr>
      			</xsl:for-each>
      		</tbody>
		</table>
	</xsl:template>

</xsl:stylesheet> 