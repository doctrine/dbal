<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\DBAL\Platforms;

/**
 * Platform to ensure compatibility of Doctrine with the old SQLServer2005 version.
 *
 * Differences to SQL Server 2008 are:
 *
 * - DATETIME2 datatype does not exist, only DATETIME which has a precision of
 *   3. This is not supported by PHP DateTime, so we are emulating it by
 *   setting .000 manually.
 */
class SQLServer2005Platform extends MsSqlPlatform
{
    public function getDateTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        // 3 - microseconds precision length
        // http://msdn.microsoft.com/en-us/library/ms187819.aspx
        return 'DATETIME';
    }

    /**
     * @override
     */
    public function getDateTimeFormatString()
    {
        return 'Y-m-d H:i:s.000';
    }
}

