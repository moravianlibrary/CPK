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
		<table class='table' data-toggle="table" data-height="299">
			<thead>
				<tr>
					<th>Part</th>
					<th>File</th>
					<th>Assertions</th>
					<th>Failures</th>
					<th>Errors</th>
					<th>Time</th>
				</tr>
			</thead>
			<tbody>
				<xsl:for-each select="testsuites/testsuite/testsuite">
   	    	 		<tr>
   		       			<td><xsl:value-of select="testcase/@class"/></td>
          				<td><xsl:value-of select="testcase/@file"/></td>
  	        			<td><span class="label label-success"><xsl:value-of select="testcase/@assertions"/></span></td>
          				<td><span class="label label-danger"><xsl:value-of select="testcase/@failures"/></span></td>
          				<td><span class="label label-danger"><xsl:value-of select="testcase/@errors"/></span></td>
          				<td><xsl:value-of select="testcase/@time"/></td>
        			</tr>
      			</xsl:for-each>
      		</tbody>
		</table>
	</xsl:template>

</xsl:stylesheet> 